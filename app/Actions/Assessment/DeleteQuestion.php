<?php

declare(strict_types=1);

namespace App\Actions\Assessment;

use App\Exceptions\AssessmentDeletionException;
use App\Models\Question;
use App\Models\User;
use App\Services\Assessment\AssessmentCounterService;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Delete a question. Options cascade via the database FK
 * (`question_options.question_id` CASCADE). `attempt_answers.question_id` is
 * RESTRICT, so a question a student has already answered cannot be deleted
 * out from under their attempt — the same protection DeleteAssessment gives
 * assessments with attempts, one level down. Checked explicitly rather than
 * left to surface as a raw constraint violation.
 */
final class DeleteQuestion
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AssessmentCounterService $counters,
    ) {}

    /**
     * @throws AssessmentDeletionException
     */
    public function handle(Question $question, User $actor): void
    {
        if ($question->answers()->exists()) {
            throw new AssessmentDeletionException(sprintf(
                'Question #%d cannot be deleted: a student has already answered it.',
                $question->id,
            ));
        }

        $assessment = $question->assessment ?? throw new RuntimeException(
            "Question #{$question->id} has no assessment — the FK constraint should make this impossible.",
        );
        $id = $question->id;

        DB::transaction(function () use ($question): void {
            $question->delete();
        });

        $this->counters->refresh($assessment);

        $this->audit->record(
            action: 'question.deleted',
            actor: $actor,
            description: "Deleted question #{$id} from \"{$assessment->title}\".",
        );
    }
}
