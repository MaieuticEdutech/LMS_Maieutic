<?php

declare(strict_types=1);

namespace App\Services\Assessment;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use Carbon\CarbonImmutable;

/**
 * The server-authoritative deadline (architecture.md §10.3, FR-ASMT-10).
 * `expires_at` is computed here ONCE at attempt start and never trusts a
 * client-reported time. The client's visible countdown is decoration; this
 * class is the only thing that decides whether an answer or a submission
 * arrived in time.
 */
final class AttemptClock
{
    /**
     * The deadline for a new attempt, or null when the assessment has no
     * time limit.
     */
    public function computeExpiry(Assessment $assessment, CarbonImmutable $startedAt): ?CarbonImmutable
    {
        if ($assessment->time_limit_minutes === null) {
            return null;
        }

        return $startedAt->addMinutes($assessment->time_limit_minutes);
    }

    /**
     * Is this attempt still within its deadline right now? An attempt with
     * no deadline (`expires_at` null — untimed) is always within it.
     */
    public function withinDeadline(AssessmentAttempt $attempt): bool
    {
        if ($attempt->expires_at === null) {
            return true;
        }

        return CarbonImmutable::now()->lessThanOrEqualTo($attempt->expires_at);
    }
}
