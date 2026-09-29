<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Marital status, as stated on a person's own profile.
 *
 * Optional profile data within NFR-DATA-01. Presentation only — it gates
 * nothing and is never consulted in an authorisation decision.
 *
 * Adding a case here requires updating the CHECK constraint on `users`
 * (2026_09_28_100100_add_personal_details_to_users_table).
 */
enum MaritalStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case Separated = 'separated';
    case Divorced = 'divorced';
    case Widowed = 'widowed';
    case PreferNotToSay = 'prefer_not_to_say';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single',
            self::Married => 'Married',
            self::Separated => 'Separated',
            self::Divorced => 'Divorced',
            self::Widowed => 'Widowed',
            self::PreferNotToSay => 'Prefer not to say',
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
