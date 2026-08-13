<?php

declare(strict_types=1);

namespace App\Services\Assessment\Handlers;

use App\Enums\QuestionType;
use App\Models\AttemptAnswer;
use App\Models\Question;

/**
 * At least one correct option (FR-ASMT-07). Grading is all-or-nothing: the
 * selected set must exactly equal the correct set — no partial credit for a
 * partly-right subset, which no FR-ASMT-* requirement asks for and would be
 * a scoring-policy decision of its own if it were.
 */
final class MultipleChoiceHandler extends AbstractChoiceHandler
{
    public function type(): QuestionType
    {
        return QuestionType::MultipleChoice;
    }

    public function label(): string
    {
        return 'Multiple choice';
    }

    public function editorView(): string
    {
        return 'admin.assessments.questions.multiple-choice';
    }

    /**
     * @param  list<array{body: string, is_correct: bool}>  $options
     * @return list<string>
     */
    public function validateAnswerKey(array $options): array
    {
        $correct = array_filter($options, static fn (array $o): bool => $o['is_correct']);

        return $correct === []
            ? ['A multiple-choice question must have at least one correct option.']
            : [];
    }

    public function grade(Question $question, AttemptAnswer $answer): bool
    {
        $selected = array_map('intval', $answer->selected_option_ids ?? []);
        sort($selected);

        $correct = $this->correctOptionIds($question);
        sort($correct);

        return $selected === $correct;
    }
}
