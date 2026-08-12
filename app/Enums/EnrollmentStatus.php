<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a student's course access grant (architecture.md §6.4, §12.2).
 * `EnrollmentAccessService::grantsAccess()` (Govind's single-owner
 * component) treats `active` and `completed` as access-granting; every other
 * status does not.
 *
 * Backed values are stored in `enrollments.status` and enforced by a
 * database CHECK constraint (ADR-012).
 */
enum EnrollmentStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Completed = 'completed';
    case Expired = 'expired';
    case Refunded = 'refunded';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
