<?php

declare(strict_types=1);

namespace App\Services\Assessment;

use App\Enums\AnswerRevealPolicy;
use App\Models\AssessmentAttempt;
use App\Models\Question;
use RuntimeException;

/**
 * The ONLY thing allowed to turn a Question into a student-facing payload
 * (NFR-SEC-21, AC-23, architecture.md §10.3). Every question handed to the
 * runner or the result screen goes through here — never `$question->toArray()`,
 * never a raw Eloquent Resource, both of which would risk leaking
 * `question_options.is_correct` or `questions.meta.accepted_answers`.
 */
final class QuestionPresenter
{
    public function __construct(private readonly QuestionTypeRegistry $registry) {}

    /**
     * Before submission — never reveals the answer key, regardless of the
     * assessment's answer-reveal policy. That policy governs the REVIEW
     * screen only; it never weakens what the runner itself sends.
     *
     * @return array<string, mixed>
     */
    public function forAttempt(Question $question): array
    {
        return $this->registry->for($question->type)->presentForStudent($question);
    }

    /**
     * After submission, honouring AnswerRevealPolicy (FR-ASMT-14, AC-27):
     *   never        — same as forAttempt(), still no answer key.
     *   after_submit — reveal correctness for every attempt once graded.
     *   after_pass   — reveal only once this specific attempt passed.
     *
     * @return array<string, mixed>
     */
    public function forReview(Question $question, AssessmentAttempt $attempt): array
    {
        $payload = $this->forAttempt($question);

        if (! $this->mayReveal($attempt)) {
            return $payload;
        }

        $payload['explanation'] = $question->explanation;

        if (isset($payload['options'])) {
            $payload['options'] = $question->options->map(static fn ($o): array => [
                'id' => $o->id,
                'body' => $o->body,
                'is_correct' => $o->is_correct,
            ])->all();
        } else {
            $payload['accepted_answers'] = $question->meta['accepted_answers'] ?? [];
        }

        return $payload;
    }

    /**
     * Whether THIS attempt may see correctness detail at all — the answer
     * key (above) and, per the attempt_answers migration's own docblock,
     * this attempt's own per-question is_correct/marks_awarded too. The
     * aggregate score (FR-ASMT-13) is never gated by this; only the
     * per-question breakdown is.
     */
    public function mayReveal(AssessmentAttempt $attempt): bool
    {
        $assessment = $attempt->assessment ?? throw new RuntimeException(
            "Attempt #{$attempt->id} has no assessment — the FK constraint should make this impossible.",
        );

        return match ($assessment->answer_reveal) {
            AnswerRevealPolicy::Never => false,
            AnswerRevealPolicy::AfterSubmit => true,
            AnswerRevealPolicy::AfterPass => $attempt->is_passed === true,
        };
    }
}
