<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Exceptions\ReorderException;
use App\Models\Course;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Reorder a course's modules by drag-and-drop (FR-CNT-03, FR-CNT-04).
 *
 * TWO PROPERTIES THIS MUST HAVE, AND WHY:
 *
 * 1. THE ID SET MUST MATCH EXACTLY. The submitted list has to contain every
 *    current module and nothing else. Not a subset, not a superset, no
 *    strangers.
 *
 *    A subset means the client's view is stale — someone added a module in
 *    another tab — and applying it would leave the missing modules with
 *    positions that collide with the reordered ones. A superset containing an
 *    id from ANOTHER COURSE would silently adopt it, which is a data-integrity
 *    hole reachable from a form post.
 *
 *    So the whole operation is rejected and the client reloads. A stale
 *    reorder is a UI inconvenience; a partial one is corruption.
 *
 * 2. IT MUST BE ATOMIC. All positions change or none do (FR-CNT-04). A
 *    half-applied reorder leaves duplicate positions, and duplicate positions
 *    make the curriculum order non-deterministic — the kind of bug that looks
 *    like "the lessons keep shuffling" and is miserable to diagnose.
 */
final class ReorderModules
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<int>  $orderedIds  Module ids in their new order.
     *
     * @throws ReorderException when the set does not match exactly.
     */
    public function handle(Course $course, array $orderedIds, User $actor): void
    {
        // Cast to int explicitly: some PDO drivers return bigint keys as
        // strings, and a string/int mismatch here would make the exact-set
        // comparison below fail for a legitimate reorder.
        $current = array_values(
            $course->modules()
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all(),
        );

        $this->assertExactMatch($current, $orderedIds);

        DB::transaction(function () use ($course, $orderedIds): void {
            foreach ($orderedIds as $position => $id) {
                $course->modules()
                    ->whereKey($id)
                    ->update(['position' => $position, 'updated_at' => now()]);
            }
        });

        $this->audit->record(
            action: 'course.modules.reordered',
            actor: $actor,
            subject: $course,
            changes: ['after' => ['order' => $orderedIds]],
            description: "Reordered modules in \"{$course->title}\".",
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
            throw new ReorderException('The submitted order contains duplicate modules.');
        }

        sort($current);
        $sortedSubmitted = $submitted;
        sort($sortedSubmitted);

        if ($current !== $sortedSubmitted) {
            throw new ReorderException(
                'The submitted order does not match this course\'s modules. '
                .'The page may be out of date — reload and try again.',
            );
        }
    }
}
