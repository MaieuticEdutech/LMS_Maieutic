<?php

declare(strict_types=1);

namespace App\Actions\Assessment;

use App\Models\Question;
use App\Models\User;
use App\Services\Assessment\AssessmentCounterService;
use App\Services\Assessment\QuestionTypeRegistry;
use App\Services\Audit\AuditLogger;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Update a question (FR-ASMT-04, FR-ASMT-05). `type` is NOT updatable —
 * same reasoning as UpdateLesson: changing a single-choice question into
 * short answer would orphan its options and invalidate any answers already
 * recorded against it. Delete and recreate instead.
 *
 * Options (choice types) are replaced wholesale when supplied — simplest
 * correct approach for an authoring-time edit, and cheap since a question
 * rarely has more than a handful of options.
 */
final class UpdateQuestion
{
    public function __construct(
        private readonly QuestionTypeRegistry $registry,
        private readonly AuditLogger $audit,
        private readonly AssessmentCounterService $counters,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @param  array{
     *     body?: string,
     *     explanation?: string|null,
     *     marks?: float|int|string,
     *     negative_marks?: float|int|string,
     *     options?: list<array{body: string, is_correct: bool}>,
     *     accepted_answers?: list<string>,
     * }  $attributes
     *
     * @throws InvalidArgumentException when the answer key fails FR-ASMT-07
     */
    public function handle(Question $question, array $attributes, User $actor): Question
    {
        $handler = $this->registry->for($question->type);
        $before = $question->only(['body', 'marks', 'negative_marks']);

        if ($handler->requiresOptions() && array_key_exists('options', $attributes)) {
            $errors = $handler->validateAnswerKey($attributes['options']);

            if ($errors !== []) {
                throw new InvalidArgumentException(implode(' ', $errors));
            }
        }

        DB::transaction(function () use ($question, $attributes, $handler): void {
            $fillable = [];

            if (array_key_exists('body', $attributes)) {
                $fillable['body'] = (string) $this->sanitizer->plainText($attributes['body']);
            }

            if (array_key_exists('explanation', $attributes)) {
                $fillable['explanation'] = $this->sanitizer->plainText($attributes['explanation']);
            }

            if (array_key_exists('marks', $attributes)) {
                $fillable['marks'] = $attributes['marks'];
            }

            if (array_key_exists('negative_marks', $attributes)) {
                $fillable['negative_marks'] = $attributes['negative_marks'];
            }

            if (! $handler->requiresOptions() && array_key_exists('accepted_answers', $attributes)) {
                $fillable['meta'] = ['accepted_answers' => $attributes['accepted_answers']];
            }

            $question->fill($fillable)->save();

            if ($handler->requiresOptions() && array_key_exists('options', $attributes)) {
                $question->options()->delete();

                foreach ($attributes['options'] as $i => $option) {
                    $question->options()->create([
                        'body' => (string) $this->sanitizer->plainText($option['body']),
                        'is_correct' => $option['is_correct'],
                        'position' => $i,
                    ]);
                }
            }
        });

        $question->refresh();
        $assessment = $question->assessment ?? throw new RuntimeException(
            "Question #{$question->id} has no assessment — the FK constraint should make this impossible.",
        );
        $this->counters->refresh($assessment);

        $this->audit->record(
            action: 'question.updated',
            actor: $actor,
            subject: $question,
            changes: ['before' => $before, 'after' => $question->only(array_keys($before))],
            description: "Updated question #{$question->id}.",
        );

        return $question;
    }
}
