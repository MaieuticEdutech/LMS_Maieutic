<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Complete account activation: validate the one-time token, set the user's
 * first password, and make the account usable (FR-AUTH-06, FR-MAIL-04).
 *
 * SECURITY PROPERTIES, all delegated to the password broker rather than
 * reimplemented (ADR-004):
 *   - the token is compared against a HASHED stored value
 *   - it expires (config lms.auth.activation_link_ttl)
 *   - it is DELETED on success, so a link works exactly once (AC-14)
 *   - reissue is throttled
 *
 * On success the account becomes fully usable in one transaction:
 *   password set · status -> Active · email marked verified · remember_token
 *   rotated.
 *
 * WHY THE EMAIL IS MARKED VERIFIED HERE (FR-MAIL-04):
 * The user proved control of the address by opening a link sent to it. That
 * is exactly what email verification tests. Requiring a second, separate
 * verification click would add friction on the critical purchase-to-access
 * path while proving nothing further.
 *
 * @return 'passwords.reset'|string Broker status string; 'passwords.reset' on success.
 */
final class ActivateAccount
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $credentials
     */
    public function handle(array $credentials): string
    {
        // Normalise so an activation link opened with different casing than
        // the stored address still resolves (FR-AUTH-10).
        $credentials['email'] = mb_strtolower(trim($credentials['email']));

        return Password::broker('activations')->reset(
            $credentials,
            function (User $user, string $password): void {
                DB::transaction(function () use ($user, $password): void {
                    $wasStatus = $user->status;

                    $user->forceFill([
                        'password' => $password,
                        'status' => UserStatus::Active,
                        'email_verified_at' => $user->email_verified_at ?? now(),
                        // Rotate: any "remember me" cookie issued before the
                        // account had a password must not survive activation.
                        'remember_token' => Str::random(60),
                    ])->save();

                    $this->audit->record(
                        action: 'user.activated',
                        actor: $user,
                        subject: $user,
                        changes: [
                            'before' => ['status' => $wasStatus->value],
                            'after' => ['status' => UserStatus::Active->value],
                        ],
                        description: "Account activated and first password set for {$user->email}.",
                    );
                });

                event(new PasswordReset($user));
            },
        );
    }
}
