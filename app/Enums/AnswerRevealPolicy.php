<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Controls when a student may see which options were correct (FR-ASMT-14,
 * AC-27). Enforced by a `QuestionPresenter` from Phase 8 onward — this enum
 * only records the configured policy; `is_correct` itself is never
 * serialised to a student before submission regardless of this setting
 * (NFR-SEC-21, AC-23).
 *
 * Backed values are stored in `assessments.answer_reveal` and enforced by a
 * database CHECK constraint (ADR-012).
 */
enum AnswerRevealPolicy: string
{
    case Never = 'never';
    case AfterSubmit = 'after_submit';
    case AfterPass = 'after_pass';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $policy): string => $policy->value, self::cases());
    }
}
