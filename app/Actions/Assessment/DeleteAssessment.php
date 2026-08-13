<?php

declare(strict_types=1);

namespace App\Actions\Assessment;

use App\Exceptions\AssessmentDeletionException;
use App\Models\Assessment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Delete an assessment (hard delete — Assessment carries no SoftDeletes;
 * questions/options cascade via the database FK).
 *
 * AN ASSESSMENT WITH ATTEMPTS CANNOT BE DELETED. `assessment_attempts.assessment_id`
 * is RESTRICT, not CASCADE (that migration's own docblock) — deliberately,
 * so a deleted assessment cannot retroactively erase a student's grading
 * history. Checked explicitly here rather than left to surface as a raw
 * constraint violation, same shape as DeleteCourse's enrollment guard.
 */
final class DeleteAssessment
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AssessmentDeletionException
     */
    public function handle(Assessment $assessment, User $actor): void
    {
        if ($assessment->attempts()->exists()) {
            throw new AssessmentDeletionException(sprintf(
                '"%s" cannot be deleted: students have attempted it. Unpublish it instead — '
                .'that removes it from view while preserving the grading history already recorded.',
                $assessment->title,
            ));
        }

        $title = $assessment->title;

        DB::transaction(function () use ($assessment): void {
            $assessment->delete();
        });

        $this->audit->record(
            action: 'assessment.deleted',
            actor: $actor,
            description: "Deleted assessment \"{$title}\".",
        );
    }
}
