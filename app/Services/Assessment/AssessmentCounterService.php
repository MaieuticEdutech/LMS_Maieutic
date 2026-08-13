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
        $totals = $assessment->questions()
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(marks), 0) as total_marks')
            ->first();

        $assessment->forceFill([
            'questions_count' => (int) ($totals->count ?? 0),
            'total_marks' => (float) ($totals->total_marks ?? 0),
        ])->save();
    }
}
