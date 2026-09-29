<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a person is addressed (Mr, Ms, …) on their own profile.
 *
 * Optional profile data within NFR-DATA-01. Presentation only — it gates
 * nothing and is never consulted in an authorisation decision.
 *
 * Adding a case here requires updating the CHECK constraint on `users`
 * (2026_09_28_100100_add_personal_details_to_users_table).
 */
enum PersonTitle: string
{
    case Mr = 'mr';
    case Ms = 'ms';
    case Mrs = 'mrs';
    case Mx = 'mx';
    case Dr = 'dr';

    public function label(): string
    {
        return match ($this) {
            self::Mr => 'Mr',
            self::Ms => 'Ms',
            self::Mrs => 'Mrs',
            self::Mx => 'Mx',
            self::Dr => 'Dr',
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
