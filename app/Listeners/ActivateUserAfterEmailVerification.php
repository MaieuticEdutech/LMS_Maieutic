<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Verified;

/**
 * Promote a self-registered account to `active` once its email is verified
 * (FR-AUTH-11, architecture.md §7.2).
 *
 * Fortify marks the email verified; it has no concept of our status lifecycle.
 * This listener closes that gap, so the state machine transition
 * PendingVerification -> Active happens in exactly one place.
 *
 * DELIBERATELY NARROW: only PendingVerification is promoted. A verification
 * event for an Inactive or Suspended account must NOT quietly reactivate it —
 * that would let a suspended user restore their own access simply by
 * re-verifying their address, turning an email click into a privilege change.
 */
final class ActivateUserAfterEmailVerification
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        if ($user->status !== UserStatus::PendingVerification) {
            return;
        }

        $user->forceFill(['status' => UserStatus::Active])->save();

        $this->audit->record(
            action: 'user.email.verified',
            actor: $user,
            subject: $user,
            changes: [
                'before' => ['status' => UserStatus::PendingVerification->value],
                'after' => ['status' => UserStatus::Active->value],
            ],
            description: "Email verified and account activated for {$user->email}.",
        );
    }
}
