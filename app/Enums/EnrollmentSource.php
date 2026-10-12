<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How an enrollment came to exist (architecture.md §6.4). `Purchase` is the
 * normal path via `GrantEnrollment` after a verified payment; `AdminGrant`
 * is an audited manual grant (Rule 21–22, ADR-006); `FreePromotion` is a
 * student's own audited claim while a free-access promotion is running
 * (planning.md §16.4); `Import` covers bulk data migration.
 *
 * Backed values are stored in `enrollments.source` and enforced by a
 * database CHECK constraint (ADR-012).
 */
enum EnrollmentSource: string
{
    case Purchase = 'purchase';
    case AdminGrant = 'admin_grant';
    case Import = 'import';
    case FreePromotion = 'free_promotion';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $source): string => $source->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::AdminGrant => 'Admin grant',
            self::Import => 'Import',
            self::FreePromotion => 'Free promotion',
        };
    }
}
