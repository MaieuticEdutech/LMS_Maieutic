<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A person's gender, as they choose to state it on their profile.
 *
 * "Prefer not to say" is a real answer, distinct from leaving it blank.
 *
 * Optional profile data within NFR-DATA-01. Presentation only — it gates
 * nothing and is never consulted in an authorisation decision.
 *
 * Adding a case here requires updating the CHECK constraint on `users`
 * (2026_09_28_100100_add_personal_details_to_users_table).
 */
enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';
    case PreferNotToSay = 'prefer_not_to_say';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
            self::Other => 'Other',
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
