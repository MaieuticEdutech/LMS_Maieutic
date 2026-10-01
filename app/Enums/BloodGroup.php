<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * ABO/Rh blood group, as stated on a person's own profile.
 *
 * Optional profile data within NFR-DATA-01. Presentation only — it gates
 * nothing and is never consulted in an authorisation decision.
 *
 * Adding a case here requires updating the CHECK constraint on `users`
 * (2026_09_28_100100_add_personal_details_to_users_table).
 */
enum BloodGroup: string
{
    case APositive = 'A+';
    case ANegative = 'A-';
    case BPositive = 'B+';
    case BNegative = 'B-';
    case AbPositive = 'AB+';
    case AbNegative = 'AB-';
    case OPositive = 'O+';
    case ONegative = 'O-';

    public function label(): string
    {
        return match ($this) {
            self::APositive => 'A+',
            self::ANegative => 'A−',
            self::BPositive => 'B+',
            self::BNegative => 'B−',
            self::AbPositive => 'AB+',
            self::AbNegative => 'AB−',
            self::OPositive => 'O+',
            self::ONegative => 'O−',
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
