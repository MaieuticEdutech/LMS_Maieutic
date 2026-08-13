<?php

declare(strict_types=1);

namespace App\Actions\Assessment;

use App\Enums\AttemptStatus;
use App\Exceptions\AttemptNotAllowedException;
use App\Models\AssessmentAttempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Services\Assessment\AttemptClock;

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

        return AttemptAnswer::query()->updateOrCreate(
            ['attempt_id' => $attempt->getKey(), 'question_id' => $question->getKey()],
            [
                'selected_option_ids' => $payload['selected_option_ids'] ?? null,
                'answer_text' => $payload['answer_text'] ?? null,
                'answered_at' => now(),
            ],
        );
    }
}
