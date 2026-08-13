<?php

declare(strict_types=1);

namespace App\Services\Assessment;

use App\Models\Assessment;

/**
 * Decides whether an assessment is ready to publish (FR-ASMT-08). Same
 * "one implementation, two consumers" shape as
 * App\Services\Content\CoursePublishValidator: the authoring UI calls
 * blockers() for a live checklist, PublishAssessment calls it to enforce.
 */
final class AssessmentPublishValidator
{
    /**
     * @return list<string>
     */
    public function blockers(Assessment $assessment): array
    {
        $blockers = [];

        if ($assessment->questions_count === 0) {
            $blockers[] = 'The assessment needs at least one question.';
        }

        if ((float) $assessment->total_marks <= 0.0) {
            $blockers[] = 'The assessment needs a total marks value above zero.';
        }

        if ($assessment->passing_percentage < 0 || $assessment->passing_percentage > 100) {
            $blockers[] = 'The passing percentage must be between 0 and 100.';
        }

        return $blockers;
    }

    public function passes(Assessment $assessment): bool
    {
        return $this->blockers($assessment) === [];
    }
}
