<?php

declare(strict_types=1);

namespace App\Actions\Assessment;

use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\Question;
use App\Models\User;
use App\Services\Assessment\AssessmentCounterService;
use App\Services\Assessment\QuestionTypeRegistry;
use App\Services\Audit\AuditLogger;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Add a question to an assessment (FR-ASMT-04, FR-ASMT-05). The question's
 * TYPE decides its behaviour via the registry — this action never branches
 * on QuestionType itself (mirrors CreateLesson's use of ContentTypeRegistry).
 *
 * FR-ASMT-07's "exactly one correct" / "at least one correct" rule is
 * enforced by asking the type's own handler, so a new type can bring its own
 * answer-key rule without editing this class.
 */
final class CreateQuestion
{
    public function __construct(
        private readonly QuestionTypeRegistry $registry,
        private readonly AuditLogger $audit,
        private readonly AssessmentCounterService $counters,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @param  array{
     *     type: QuestionType,
     *     body: string,
     *     explanation?: string|null,
     *     marks: float|int|string,
     *     negative_marks?: float|int|string,
     *     options?: list<array{body: string, is_correct: bool}>,
     *     accepted_answers?: list<string>,
     * }  $attributes
     *
     * @throws InvalidArgumentException when the answer key fails FR-ASMT-07
     */
    public function handle(Assessment $assessment, array $attributes, User $actor): Question
    {
        $type = $attributes['type'];
        $handler = $this->registry->for($type);

        $options = $attributes['options'] ?? [];

        if ($handler->requiresOptions()) {
            $errors = $handler->validateAnswerKey($options);

            if ($errors !== []) {
                throw new InvalidArgumentException(implode(' ', $errors));
            }
        }

        $question = DB::transaction(function () use ($assessment, $attributes, $type, $handler, $options): Question {
            $meta = $handler->requiresOptions()
                ? null
                : ['accepted_answers' => $attributes['accepted_answers'] ?? []];

            $question = new Question;

            $question->fill([
                'type' => $type,
                'body' => (string) $this->sanitizer->plainText($attributes['body']),
                'explanation' => $this->sanitizer->plainText($attributes['explanation'] ?? null),
                'marks' => $attributes['marks'],
                'negative_marks' => $attributes['negative_marks'] ?? 0,
                'position' => $this->nextPosition($assessment),
                'meta' => $meta,
            ]);

            $question->assessment()->associate($assessment);
            $question->save();

            if ($handler->requiresOptions()) {
                foreach ($options as $i => $option) {
                    $question->options()->create([
                        'body' => (string) $this->sanitizer->plainText($option['body']),
                        'is_correct' => $option['is_correct'],
                        'position' => $i,
                    ]);
                }
            }

            return $question;
        });

        $this->counters->refresh($assessment);

        $this->audit->record(
            action: 'question.created',
            actor: $actor,
            subject: $question,
            description: sprintf('Added a %s question to "%s".', $type->value, $assessment->title),
        );

        return $question;
    }

    private function nextPosition(Assessment $assessment): int
    {
        return (int) $assessment->questions()->max('position') + 1;
    }
}
