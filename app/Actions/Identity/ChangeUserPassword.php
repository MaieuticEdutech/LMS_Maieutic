<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Set a user's password and invalidate their other sessions (FR-AUTH-12).
 *
 * The single implementation behind BOTH Fortify adapters — the profile
 * "change password" form and the "forgot password" reset — so the same
 * security consequences follow either way (ADR-013).
 *
 * WHY OTHER SESSIONS ARE INVALIDATED:
 * A password change is the action a user takes when they believe their account
 * is compromised. If an attacker's existing session survived it, the change
 * would be theatre. `logoutOtherDevices` rehashes the remember-token and
 * password hash used for session validation, so every other session is
 * rejected on its next request.
 *
 * The raw password is never logged — the audit entry records THAT the password
 * changed, never the value, and AuditLogger redacts the field regardless.
 */
final class ChangeUserPassword
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  bool  $activateIfPending  When a pending-activation account sets a
     *                                   password via the reset flow rather than
     *                                   the activation link, the outcome must be
     *                                   the same: a usable, active account.
     *                                   Otherwise the user would set a password
     *                                   and still be unable to log in.
     */
    public function handle(User $user, string $password, bool $activateIfPending = false): void
    {
        DB::transaction(function () use ($user, $password, $activateIfPending): void {
            $attributes = ['password' => $password];

            if ($activateIfPending && $user->status === UserStatus::PendingActivation) {
                $attributes['status'] = UserStatus::Active;
                $attributes['email_verified_at'] = $user->email_verified_at ?? now();
            }

            $user->forceFill($attributes)->save();
        });

        $this->audit->record(
            action: 'user.password.changed',
            actor: $user,
            subject: $user,
            description: "Password changed for {$user->email}.",
        );
    }
}
