<?php

declare(strict_types=1);

namespace App\Actions\Assessment;

use App\Exceptions\AssessmentPublishException;
use App\Models\Assessment;
use App\Models\User;
use App\Services\Assessment\AssessmentPublishValidator;
use App\Services\Audit\AuditLogger;

/**
 * Publish an assessment, enforced by AssessmentPublishValidator (FR-ASMT-08).
 * One implementation, two consumers — see that class's docblock.
 */
final class PublishAssessment
{
    public function __construct(
        private readonly AssessmentPublishValidator $validator,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws AssessmentPublishException
     */
    public function handle(Assessment $assessment, User $actor): Assessment
    {
        $blockers = $this->validator->blockers($assessment);

        if ($blockers !== []) {
            throw new AssessmentPublishException($blockers);
        }

        $assessment->is_published = true;
        $assessment->save();

        $this->audit->record(
            action: 'assessment.published',
            actor: $actor,
            subject: $assessment,
            description: "Published assessment \"{$assessment->title}\".",
        );

        return $assessment;
    }
}
