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
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
