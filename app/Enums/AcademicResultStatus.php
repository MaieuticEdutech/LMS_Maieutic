<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether a stated academic result has been declared yet.
 *
 * Optional profile data within NFR-DATA-01. Presentation only — it gates
 * nothing and is never consulted in an authorisation decision.
 *
 * Adding a case here requires updating the CHECK constraints on `users`
 * (2026_09_28_100400_add_academic_details_to_users_table).
 */
enum AcademicResultStatus: string
{
    case Declared = 'declared';
    case Awaited = 'awaited';

    public function label(): string
    {
        return match ($this) {
            self::Declared => 'Declared',
            self::Awaited => 'Awaited',
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
