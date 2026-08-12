<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a student's assessment attempt (architecture.md §10.2,
 * FR-ASMT-16). `in_progress` is the state the partial unique index on
 * `assessment_attempts` guards — a student may have at most one per
 * assessment at a time (AC-26).
 *
 * Backed values are stored in `assessment_attempts.status` and enforced by
 * a database CHECK constraint (ADR-012).
 */
enum AttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Graded = 'graded';
    case Expired = 'expired';
    case Abandoned = 'abandoned';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
