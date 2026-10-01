<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether a stated academic score is a percentage or a CGPA.
 *
 * Optional profile data within NFR-DATA-01. Presentation only — it gates
 * nothing and is never consulted in an authorisation decision. Also decides
 * the bound ProfileForm validates the paired score against (0–100 or 0–10).
 *
 * Adding a case here requires updating the CHECK constraints on `users`
 * (2026_09_28_100400_add_academic_details_to_users_table).
 */
enum MarkingScheme: string
{
    case Percentage = 'percentage';
    case Cgpa = 'cgpa';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage',
            self::Cgpa => 'CGPA',
        };
    }

    /** The upper bound a score on this scheme can reach. */
    public function maxScore(): float
    {
        return match ($this) {
            self::Percentage => 100.0,
            self::Cgpa => 10.0,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
