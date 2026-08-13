<?php

declare(strict_types=1);

namespace App\Services\Assessment\Handlers;

use App\Enums\QuestionType;
use App\Models\AttemptAnswer;
use App\Models\Question;

/**
 * Exactly one correct option; a correct answer selects exactly that one
 * (FR-ASMT-07).
 */
final class SingleChoiceHandler extends AbstractChoiceHandler
{
    public function type(): QuestionType
    {
        return QuestionType::SingleChoice;
    }

    public function label(): string
    {
        return 'Single choice';
    }

    public function editorView(): string
    {
        return 'admin.assessments.questions.single-choice';
    }

    /**
     * @param  list<array{body: string, is_correct: bool}>  $options
     * @return list<string>
     */
    public function validateAnswerKey(array $options): array
    {
        $correct = array_filter($options, static fn (array $o): bool => $o['is_correct']);

        return count($correct) === 1
            ? []
            : ['A single-choice question must have exactly one correct option.'];
    }

    public function grade(Question $question, AttemptAnswer $answer): bool
    {
        $selected = $answer->selected_option_ids ?? [];

        if (count($selected) !== 1) {
            return false;
        }

        return $this->correctOptionIds($question) === array_map('intval', $selected);
    }
}
