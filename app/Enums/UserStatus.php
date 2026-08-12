<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Account lifecycle states (architecture.md §7.2).
 *
 *   [*] -> PendingActivation    created by a verified purchase (Phase 12)
 *   [*] -> PendingVerification  self-registration
 *   PendingVerification -> Active   email verified
 *   PendingActivation   -> Active   password set via one-time link
 *   Active <-> Inactive             admin deactivates / reactivates
 *   Active <-> Suspended            admin suspends / reinstates
 *
 * ONLY `Active` MAY AUTHENTICATE. This is asserted inside
 * Fortify::authenticateUsing() — not merely in middleware — so a suspended
 * user never reaches an established session (architecture.md §7.1.1).
 *
 * Two states look similar but are not:
 *   PendingVerification — the user chose a password; we do not yet trust the
 *       email address. Resolved by clicking a verification link.
 *   PendingActivation   — we trust the email (they paid with it); the user has
 *       no password at all. Resolved by setting one via a one-time link.
 *       `users.password` is NULL, so authentication is structurally impossible.
 */
enum UserStatus: string
{
    case PendingVerification = 'pending_verification';
    case PendingActivation = 'pending_activation';
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::PendingVerification => 'Pending email verification',
            self::PendingActivation => 'Pending activation',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Suspended => 'Suspended',
        };
    }

    /**
     * Badge variant for the shared <x-badge> component.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::PendingVerification, self::PendingActivation => 'warning',
            self::Inactive => 'neutral',
            self::Suspended => 'danger',
        };
    }

    /**
     * May an account in this state authenticate?
     *
     * The single definition, consumed by authenticateUsing() and by the
     * EnsureUserIsActive middleware, so the two can never disagree.
     */
    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }

    /**
     * States an administrator may switch between directly (Phase 4).
     *
     * The two pending states are absent: they are resolved by the user
     * completing verification or activation, not by an admin toggling them.
     *
     * @return list<self>
     */
    public static function adminAssignable(): array
    {
        return [self::Active, self::Inactive, self::Suspended];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
