<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * MVP question types (FR-ASMT-04). `SingleChoice` and `TrueFalse` require
 * exactly one correct option; `MultipleChoice` requires at least one;
 * `ShortAnswer` is graded against `questions.meta`'s accepted answers rather
 * than `question_options` (FR-ASMT-07, architecture.md §6.4).
 *
 * Backed values are stored in `questions.type` and enforced by a database
 * CHECK constraint (ADR-012).
 */
enum QuestionType: string
{
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case TrueFalse = 'true_false';
    case ShortAnswer = 'short_answer';

    /**
     * A human name for the type.
     *
     * Every other enum in this codebase carries one; this was the exception,
     * and the question list called `->label()` on it anyway. The list is empty
     * until an assessment has its first question, so the screen rendered
     * perfectly right up until somebody successfully added one — then 500'd.
     *
     * Written out rather than derived from the backed value, because
     * "True or false" is not what `str_replace('_', ' ', 'true_false')`
     * produces, and an author should see the phrase they would say out loud.
     */
    public function label(): string
    {
        return match ($this) {
            self::SingleChoice => 'Single choice',
            self::MultipleChoice => 'Multiple choice',
            self::TrueFalse => 'True or false',
            self::ShortAnswer => 'Short answer',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
