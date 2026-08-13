<?php

declare(strict_types=1);

namespace App\Services\Assessment\Handlers;

use App\Enums\QuestionType;
use App\Models\AttemptAnswer;
use App\Models\Question;

/**
 * A constrained single-choice: exactly two options ("True"/"False"), exactly
 * one correct (FR-ASMT-07). Kept as its own type/handler rather than reusing
 * SingleChoiceHandler because the editor and player render differently (a
 * fixed two-button control, not an option list an author builds).
 */
final class TrueFalseHandler extends AbstractChoiceHandler
{
    public function type(): QuestionType
    {
        return QuestionType::TrueFalse;
    }

    public function label(): string
    {
        return 'True / False';
    }

    public function editorView(): string
    {
        return 'admin.assessments.questions.true-false';
    }

    /**
     * @param  list<array{body: string, is_correct: bool}>  $options
     * @return list<string>
     */
    public function validateAnswerKey(array $options): array
    {
        if (count($options) !== 2) {
            return ['A true/false question must have exactly two options.'];
        }

        $correct = array_filter($options, static fn (array $o): bool => $o['is_correct']);

        return count($correct) === 1
            ? []
            : ['A true/false question must have exactly one correct option.'];
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
