<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Password;

/**
 * Admin-triggered password reset (phases.md Phase 4: "force reset"). Uses
 * the standard `password_reset` broker (60m TTL) — distinct from
 * {@see \App\Actions\Identity\SendActivationLink}'s `activations` broker
 * (72h), because this is for an account that already has a password and
 * simply needs a new one, not a passwordless account getting its first.
 *
 * No password is ever generated or emailed here — only a link, the same
 * rule {@see SendActivationLink} follows.
 */
final class ForcePasswordReset
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $user, User $actor): void
    {
        Password::broker('users')->sendResetLink(['email' => $user->email]);

        $this->audit->record(
            action: 'user.password_reset.forced',
            actor: $actor,
            subject: $user,
            description: "Password reset forced for {$user->email} by {$actor->email}.",
        );
    }
}
