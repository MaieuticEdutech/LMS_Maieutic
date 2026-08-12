<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Models\User;
use App\Notifications\AccountActivationNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Password;

/**
 * Issue a one-time link that lets a purchase-created account set its first
 * password (FR-AUTH-05, FR-MAIL-02, FR-MAIL-03).
 *
 * THE RULE THIS ENFORCES:
 * A raw or generated password is NEVER emailed. Not once, not "temporarily".
 * The user chooses their own password behind a link that is stored hashed,
 * expires, and dies on first use.
 *
 * MECHANISM (ADR-004): Laravel's password broker, not a bespoke table. The
 * broker already stores tokens hashed, expires them, throttles reissue and
 * deletes on use — writing our own would mean reimplementing four security
 * properties for no gain.
 *
 * The `activations` broker is configured with a longer TTL (72h default) than
 * the `password_reset` broker (60m), because a buyer may not open their email
 * immediately after paying — and an expired link on the critical onboarding
 * path means a paying customer who cannot reach what they bought.
 *
 * Built here in Phase 2; consumed by the purchase flow in Phase 12.
 */
final class SendActivationLink
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $user): void
    {
        $token = Password::broker('activations')->createToken($user);

        $user->notify(new AccountActivationNotification($token));

        $this->audit->record(
            action: 'user.activation_link_sent',
            subject: $user,
            description: "Activation link issued to {$user->email}.",
        );
    }
}
