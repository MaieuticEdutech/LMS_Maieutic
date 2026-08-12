<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * ADR-002: there is no `quizzes` table and no `tests` table. A quiz and a
 * test are the same structure — questions, marks, pass %, time limit,
 * attempts — differing only in what they attach to: a quiz attaches to a
 * Lesson or Module, a test attaches to a Course as its final assessment
 * (FR-ASMT-01). One engine, one policy set, one discriminator column.
 *
 * Backed values are stored in `assessments.type` and enforced by a database
 * CHECK constraint (ADR-012).
 */
enum AssessmentType: string
{
    case Quiz = 'quiz';
    case Test = 'test';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
