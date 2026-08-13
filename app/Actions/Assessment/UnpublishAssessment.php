<?php

declare(strict_types=1);

namespace App\Actions\Assessment;

use App\Models\Assessment;
use App\Models\User;
use App\Services\Audit\AuditLogger;

/**
 * Unpublish an assessment. No validation needed — going back to draft is
 * always safe, and (same rule as courses) never revokes anything a student
 * already has: past attempts, scores and grading history are untouched.
 */
final class UnpublishAssessment
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Assessment $assessment, User $actor): Assessment
    {
        $assessment->is_published = false;
        $assessment->save();

        $this->audit->record(
            action: 'assessment.unpublished',
            actor: $actor,
            subject: $assessment,
            description: "Unpublished assessment \"{$assessment->title}\".",
        );

        return $assessment;
    }
}
