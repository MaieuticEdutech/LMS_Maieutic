<?php

declare(strict_types=1);

namespace App\Actions\Assessment;

use App\Enums\AttemptStatus;
use App\Exceptions\AttemptNotAllowedException;
use App\Models\AssessmentAttempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Services\Assessment\AttemptClock;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Autosave one answer (FR-ASMT-11). Upserted by (attempt_id, question_id) —
 * the unique index that makes this a safe repeated call as the student
 * changes their mind, not an append.
 *
 * DEADLINE ENFORCED HERE, NOT ONLY AT SUBMIT (FR-ASMT-10, AC-24): rejecting
 * a late save is what guarantees SubmitAttempt only ever grades answers
 * saved before the deadline — by the time submission happens, every row in
 * `attempt_answers` is already known-timely, so grading never needs to
 * filter anything out.
 */
final class SaveAnswer
{
    public function __construct(private readonly AttemptClock $clock) {}

    /**
     * @param  array{selected_option_ids?: list<int>|null, answer_text?: string|null}  $payload
     *
     * @throws AttemptNotAllowedException
     */
    public function handle(AssessmentAttempt $attempt, Question $question, array $payload): AttemptAnswer
    {
        if ($attempt->status !== AttemptStatus::InProgress) {
            throw new AttemptNotAllowedException('This attempt is no longer in progress.');
        }

        if (! $this->clock->withinDeadline($attempt)) {
            // Make the lapsed state visible immediately rather than waiting
            // for the scheduled sweep — the next thing this student sees
            // should already say "expired", not "in progress" a moment
            // longer than reality.
            $attempt->status = AttemptStatus::Expired;
            $attempt->save();

            throw new AttemptNotAllowedException('The time limit for this attempt has passed.');
        }

        if (! in_array($question->getKey(), $attempt->question_order, true)) {
            throw new AttemptNotAllowedException('That question is not part of this attempt.');
        }

        /*
         * ═════════════════════════════════════════════════════════════════
         * OWNERSHIP IS ASSIGNED, NEVER MASS-ASSIGNED (NFR-SEC-07).
         *
         * This was `updateOrCreate([attempt_id, question_id], [...])`, which
         * fills those two columns through the mass-assignment path. They are
         * deliberately absent from AttemptAnswer::$fillable — which student's
         * attempt an answer belongs to is not something a payload gets to say
         * — so with Model::preventSilentlyDiscardingAttributes active the call
         * THREW, and it is active everywhere except production.
         *
         * The effect was that autosave could not create a first answer row in
         * development, testing or CI. It went unnoticed because this action
         * had no test until Phase 8's coverage was written.
         *
         * The row is now found first and its identity assigned explicitly, so
         * the guard stays on and the write still happens.
         * ═════════════════════════════════════════════════════════════════
         */
        $answer = $this->find($attempt, $question) ?? new AttemptAnswer;

        $answer->forceFill([
            'attempt_id' => $attempt->getKey(),
            'question_id' => $question->getKey(),
            'selected_option_ids' => $payload['selected_option_ids'] ?? null,
            'answer_text' => $payload['answer_text'] ?? null,
            'answered_at' => now(),
        ]);

        try {
            $answer->save();
        } catch (UniqueConstraintViolationException $e) {
            /*
             * UNIQUE(attempt_id, question_id) reached by two saves racing for
             * a question with no row yet — realistic when an autosave and an
             * explicit save land together. The constraint is relied upon
             * rather than raced against, the same shape as GrantEnrollment:
             * re-read the winner and apply this answer to it, so the student's
             * latest choice is what survives.
             */
            $winner = $this->find($attempt, $question);

            if ($winner === null) {
                throw $e;
            }

            $winner->forceFill([
                'selected_option_ids' => $payload['selected_option_ids'] ?? null,
                'answer_text' => $payload['answer_text'] ?? null,
                'answered_at' => now(),
            ])->save();

            return $winner;
        }

        return $answer;
    }

    private function find(AssessmentAttempt $attempt, Question $question): ?AttemptAnswer
    {
        return AttemptAnswer::query()
            ->where('attempt_id', $attempt->getKey())
            ->where('question_id', $question->getKey())
            ->first();
    }
}
