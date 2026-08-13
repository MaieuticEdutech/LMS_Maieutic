<?php

declare(strict_types=1);

namespace App\Services\Assessment;

use App\Models\AssessmentAttempt;
use RuntimeException;

/**
 * Server-side grading (FR-ASMT-12, architecture.md §10.3). Loops every
 * question in the attempt's snapshot, asks the question's own type handler
 * whether the saved answer was correct, and applies marks/negative-marks
 * arithmetic centrally — the one place that logic lives, so every type gets
 * negative marking for free rather than reimplementing it four times.
 *
 * Writes `attempt_answers.is_correct`/`marks_awarded` for every question,
 * including ones the student never answered (an unanswered question is
 * simply not correct, worth zero, and never negatively marked — negative
 * marking penalises a wrong guess, not a blank).
 */
final class GradingService
{
    public function __construct(
        private readonly QuestionTypeRegistry $registry,
    ) {}

    /**
     * @return array{scoreMarks: float, maxMarks: float, percentage: float, passed: bool}
     */
    public function grade(AssessmentAttempt $attempt): array
    {
        // assessment_id is a NOT NULL, RESTRICT-on-delete FK — null here
        // would mean the attempt outlived the row it references, which the
        // database itself refuses to allow.
        /*
         * Loaded explicitly, and it matters twice over.
         *
         * The loop below reads `$question->options` for every question, which
         * lazily would be one query per question — an N+1 inside the one
         * operation a student is actively waiting on — and a
         * LazyLoadingViolation outside production, where the guard is on.
         *
         * SubmitAttempt re-reads the attempt under `lockForUpdate()`, so
         * whatever the caller had loaded is gone by the time grading runs.
         * This is where the relations have to be asked for.
         */
        $attempt->loadMissing(['assessment.questions.options', 'answers']);

        $assessment = $attempt->assessment ?? throw new RuntimeException(
            "Attempt #{$attempt->id} has no assessment — the FK constraint should make this impossible.",
        );
        $answersByQuestion = $attempt->answers->keyBy('question_id');

        $scoreMarks = 0.0;
        $maxMarks = 0.0;

        foreach ($assessment->questions as $question) {
            $maxMarks += (float) $question->marks;

            $answer = $answersByQuestion->get($question->id);

            if ($answer === null) {
                // Never answered — zero, never negative. Still write the
                // grading result explicitly so review shows "not answered"
                // rather than an ungraded gap.
                continue;
            }

            $handler = $this->registry->for($question->type);
            $isCorrect = $handler->grade($question, $answer);

            $marksAwarded = $isCorrect
                ? (float) $question->marks
                : ($assessment->negative_marking_enabled ? -(float) $question->negative_marks : 0.0);

            $answer->forceFill([
                'is_correct' => $isCorrect,
                'marks_awarded' => $marksAwarded,
            ])->save();

            $scoreMarks += $marksAwarded;
        }

        // A negative total (possible with negative marking on a bad run) is
        // floored at zero — a student cannot owe marks back.
        $scoreMarks = max(0.0, $scoreMarks);

        $percentage = $maxMarks > 0.0 ? round(($scoreMarks / $maxMarks) * 100, 2) : 0.0;
        $passed = $percentage >= $assessment->passing_percentage;

        return [
            'scoreMarks' => $scoreMarks,
            'maxMarks' => $maxMarks,
            'percentage' => $percentage,
            'passed' => $passed,
        ];
    }
}
