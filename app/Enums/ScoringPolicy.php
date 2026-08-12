<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * When multiple attempts are allowed, this decides which one counts as the
 * "official" score (FR-ASMT-15). `Highest` is the default — retaining every
 * attempt but scoring only the best one is the common LMS expectation.
 *
 * Backed values are stored in `assessments.scoring_policy` and enforced by a
 * database CHECK constraint (ADR-012).
 */
enum ScoringPolicy: string
{
    case Highest = 'highest';
    case Latest = 'latest';
    case First = 'first';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $policy): string => $policy->value, self::cases());
    }
}
