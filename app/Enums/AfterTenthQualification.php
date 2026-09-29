<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a learner did after Class Xth.
 *
 * INFORMATIONAL ONLY. Nothing in ProfileForm branches on this value — it does
 * not change which fields the academic-details section shows. Wiring the
 * profile form to switch between different qualification paths (e.g. a
 * diploma track replacing Class XIIth entirely) was explicitly scoped out
 * when this was built; this field exists so the answer is still on record.
 *
 * Optional profile data within NFR-DATA-01.
 *
 * Adding a case here requires updating the CHECK constraint on `users`
 * (2026_09_28_100400_add_academic_details_to_users_table).
 */
enum AfterTenthQualification: string
{
    case ClassXiiHsc = 'class_xii_hsc';
    case Diploma = 'diploma';
    case Iti = 'iti';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ClassXiiHsc => 'Class XII',
            self::Diploma => 'Diploma',
            self::Iti => 'ITI',
            self::Other => 'Other',
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
