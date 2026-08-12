<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Exceptions\ReorderException;
use App\Models\Module;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Reorder a module's lessons by drag-and-drop (FR-CNT-03, FR-CNT-04).
 *
 * Same two guarantees as ReorderModules — exact ID-set match, and atomicity —
 * and for the same reasons. See that class for the full rationale.
 *
 * The exact-match check matters slightly more here: lesson ids are submitted
 * per module, so a superset could pull a lesson out of a DIFFERENT module of
 * the same course, silently re-parenting it. Scoping the update to
 * `$module->lessons()` means even a forged id cannot move a lesson that does
 * not already belong to this module — the check produces a clear error, and
 * the scope is the backstop if it were ever removed.
 */
final class ReorderLessons
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<int>  $orderedIds  Lesson ids in their new order.
     *
     * @throws ReorderException when the set does not match exactly.
     */
    public function handle(Module $module, array $orderedIds, User $actor): void
    {
        // See ReorderModules: bigint keys can arrive as strings from PDO, and
        // a type mismatch would fail a legitimate reorder.
        $current = array_values(
            $module->lessons()
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all(),
        );

        $this->assertExactMatch($current, $orderedIds);

        DB::transaction(function () use ($module, $orderedIds): void {
            foreach ($orderedIds as $position => $id) {
                $module->lessons()
                    ->whereKey($id)
                    ->update(['position' => $position, 'updated_at' => now()]);
            }
        });

        $this->audit->record(
            action: 'module.lessons.reordered',
            actor: $actor,
            subject: $module,
            changes: ['after' => ['order' => $orderedIds]],
            description: "Reordered lessons in module \"{$module->title}\".",
        );
    }

    /**
     * @param  list<int>  $current
     * @param  list<int>  $submitted
     *
     * @throws ReorderException
     */
    private function assertExactMatch(array $current, array $submitted): void
    {
        if (count($submitted) !== count(array_unique($submitted))) {
            throw new ReorderException('The submitted order contains duplicate lessons.');
        }

        sort($current);
        $sortedSubmitted = $submitted;
        sort($sortedSubmitted);

        if ($current !== $sortedSubmitted) {
            throw new ReorderException(
                'The submitted order does not match this module\'s lessons. '
                .'The page may be out of date — reload and try again.',
            );
        }
    }
}
