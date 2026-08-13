<?php

declare(strict_types=1);

namespace App\Actions\Assessment;

use App\Exceptions\ReorderException;
use App\Models\Assessment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Reorder an assessment's questions. Same two guarantees as
 * App\Actions\Catalog\ReorderModules and for the same reasons: the submitted
 * ID set must match exactly (a stale or foreign ID rejects the whole
 * operation rather than corrupting order), and the update is atomic.
 */
final class ReorderQuestions
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<int>  $orderedIds
     *
     * @throws ReorderException
     */
    public function handle(Assessment $assessment, array $orderedIds, User $actor): void
    {
        $current = array_values(
            $assessment->questions()
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all(),
        );

        $this->assertExactMatch($current, $orderedIds);

        DB::transaction(function () use ($assessment, $orderedIds): void {
            foreach ($orderedIds as $position => $id) {
                $assessment->questions()
                    ->whereKey($id)
                    ->update(['position' => $position, 'updated_at' => now()]);
            }
        });

        $this->audit->record(
            action: 'assessment.questions.reordered',
            actor: $actor,
            subject: $assessment,
            changes: ['after' => ['order' => $orderedIds]],
            description: "Reordered questions in \"{$assessment->title}\".",
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
            throw new ReorderException('The submitted order contains duplicate questions.');
        }

        sort($current);
        $sortedSubmitted = $submitted;
        sort($sortedSubmitted);

        if ($current !== $sortedSubmitted) {
            throw new ReorderException(
                'The submitted order does not match this assessment\'s questions. '
                .'The page may be out of date — reload and try again.',
            );
        }
    }
}
