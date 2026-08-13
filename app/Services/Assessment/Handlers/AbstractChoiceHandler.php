<?php

declare(strict_types=1);

namespace App\Services\Assessment\Handlers;

use App\Models\Question;
use App\Services\Assessment\Contracts\QuestionTypeHandler;

/**
 * Shared behaviour for the three types whose answer key lives in
 * `question_options`: single choice, multiple choice, true/false. They
 * differ only in how many correct options are required and how a selected
 * set is compared — everything else (requiresOptions, presentForStudent) is
 * identical, so it lives here once (mirrors AbstractMediaContentHandler).
 */
abstract class AbstractChoiceHandler implements QuestionTypeHandler
{
    public function requiresOptions(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        return [];
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
            'options' => $question->options->map(static fn ($option): array => [
                'id' => $option->id,
                'body' => $option->body,
            ])->all(),
        ];
    }

    /**
     * @return list<int>
     */
    protected function correctOptionIds(Question $question): array
    {
        return array_values($question->options
            ->filter(static fn ($option): bool => $option->is_correct)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all());
    }
}
