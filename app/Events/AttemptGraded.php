<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AssessmentAttempt;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once an attempt has been graded, whether by a student submission or
 * the ExpireStaleAttempts sweep grading a lapsed one. No listeners in Phase
 * 8 — Phase 9 recalculates lesson/course progress from it, Phase 11 queues
 * a result email. Dispatched now so those phases attach a listener rather
 * than editing SubmitAttempt/ExpireStaleAttempts (architecture.md §10.3
 * sequence diagram).
 */
final class AttemptGraded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly AssessmentAttempt $attempt) {}
}
