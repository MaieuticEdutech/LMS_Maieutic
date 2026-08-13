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

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Completed => 'Completed',
            self::Expired => 'Expired',
            self::Refunded => 'Refunded',
        };
    }

    /**
     * Badge colour for the admin enrolments table (UI-GUIDE.md §5).
     *
     * STATUS COLOUR IS NEVER DECORATIVE. Green means the student can reach
     * the course; red means access was taken away and somebody did that on
     * purpose. `expired` is amber rather than red because nothing went wrong
     * — a time limit simply elapsed.
     *
     * The label always accompanies the badge, so meaning is never carried by
     * colour alone (UI-GUIDE.md §12, WCAG 2.1 AA).
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Active, self::Completed => 'success',
            self::Suspended, self::Expired => 'warning',
            self::Refunded => 'danger',
        };
    }

    /**
     * Is this status one of the two that CAN grant access?
     *
     * ═════════════════════════════════════════════════════════════════════
     * NOT AN ACCESS CHECK. NEVER USE IT AS ONE.
     *
     * The authority is `EnrollmentAccessService::grantsAccess()` (rule S-8),
     * which also weighs `expires_at` — something this enum cannot see. An
     * enrolment can be `active` and expired, and this method still returns
     * true for it.
     *
     * DELIBERATELY NOT NAMED `grantsAccess()`. That name collided with the
     * real gate, and `$enrollment->status->grantsAccess()` read exactly like
     * an authorisation check while quietly skipping expiry — the kind of
     * near-miss that gets copied into a policy by someone reading fast.
     * `isAccessGranting()` describes the STATUS, not a permission.
     *
     * Legitimate uses: labelling a row, deciding whether to offer a "suspend"
     * button. Never: deciding whether to serve a lesson.
     * ═════════════════════════════════════════════════════════════════════
     */
    public function isAccessGranting(): bool
    {
        return $this === self::Active || $this === self::Completed;
    }
}
