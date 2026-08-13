<?php

declare(strict_types=1);

namespace App\Services\Assessment\Handlers;

use App\Enums\QuestionType;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Services\Assessment\Contracts\QuestionTypeHandler;

/**
 * Graded by exact/normalised match against `questions.meta.accepted_answers`
 * (FR-ASMT-04, architecture.md §6.4) — no options row at all, unlike the
 * three choice types.
 */
final class ShortAnswerHandler implements QuestionTypeHandler
{
    public function type(): QuestionType
    {
        return QuestionType::ShortAnswer;
    }

    public function label(): string
    {
        return 'Short answer';
    }

    public function editorView(): string
    {
        return 'admin.assessments.questions.short-answer';
    }

    public function requiresOptions(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        return [
            'accepted_answers' => ['required', 'array', 'min:1'],
            'accepted_answers.*' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @param  list<array{body: string, is_correct: bool}>  $options
     * @return list<string>
     */
    public function validateAnswerKey(array $options): array
    {
        // The answer key here is `meta.accepted_answers`, already required
        // and validated by validationRules() — nothing further to check
        // against an options array, which short answer doesn't use.
        return [];
    }

    public function grade(Question $question, AttemptAnswer $answer): bool
    {
        $given = $this->normalise($answer->answer_text ?? '');

        if ($given === '') {
            return false;
        }

        $accepted = $question->meta['accepted_answers'] ?? [];

        foreach ($accepted as $candidate) {
            if ($this->normalise((string) $candidate) === $given) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function presentForStudent(Question $question): array
    {
        return [
            'id' => $question->id,
            'type' => $question->type->value,
            'body' => $question->body,
            'marks' => (float) $question->marks,
        ];
    }

    private function normalise(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }
}
