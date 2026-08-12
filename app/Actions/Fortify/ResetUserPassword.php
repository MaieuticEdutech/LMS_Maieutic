<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Actions\Identity\ChangeUserPassword;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * Fortify adapter — password reset ("forgot password" flow).
 *
 * Delegates to ChangeUserPassword so a reset has exactly the same
 * consequences as a profile password change: other sessions invalidated, an
 * audit entry written, and the raw value never logged (ADR-013).
 *
 * `activateIfPending: true` matters. A purchase-created account
 * (PendingActivation, NULL password) whose owner clicks "forgot password"
 * instead of the activation link must end up ACTIVE. Without this they would
 * set a password successfully and still be unable to log in — a dead end on
 * the paid-onboarding path with no obvious cause.
 */
final class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private readonly ChangeUserPassword $changePassword) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function reset(Authenticatable $user, array $input): void
    {
        $validated = Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        if (! $user instanceof User) {
            return;
        }

        $this->changePassword->handle(
            user: $user,
            password: $validated['password'],
            activateIfPending: true,
        );
    }
}
