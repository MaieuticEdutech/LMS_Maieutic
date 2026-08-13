<?php

declare(strict_types=1);

namespace App\Services\Assessment;

use App\Models\Assessment;

/**
 * Keeps `total_marks`/`questions_count` accurate (FR-ASMT-06) — mirrors
 * App\Services\Content\CourseCounterService's role for course counters.
 * Called by every question-mutating action; never set directly from a form.
 */
final class AssessmentCounterService
{
    public function refresh(Assessment $assessment): void
    {
        /*
         * ═════════════════════════════════════════════════════════════════
         * reorder() IS LOad-BEARING, NOT TIDYING.
         *
         * `Assessment::questions()` carries `->orderBy('position')` so every
         * ordinary read comes back in paper order. Inherited by THIS query it
         * produces `SELECT COUNT(*), SUM(marks) … ORDER BY position`, which
         * PostgreSQL rejects outright: SQLSTATE 42803, "column
         * questions.position must appear in the GROUP BY clause".
         *
         * The effect was that every question-mutating action — create, update,
         * delete, reorder — threw the moment it refreshed the counters. Quiz
         * authoring did not work at all.
         *
         * MySQL and SQLite both accept the same query, which is precisely why
         * architecture.md C-03 insists the suite runs on real PostgreSQL. It
         * survived because no test exercised this path until Phase 8's
         * coverage was written.
         * ═════════════════════════════════════════════════════════════════
         */
        $totals = $assessment->questions()
            ->reorder()
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(marks), 0) as total_marks')
            ->first();

        $assessment->forceFill([
            'questions_count' => (int) ($totals->count ?? 0),
            'total_marks' => (float) ($totals->total_marks ?? 0),
        ])->save();
    }
}
