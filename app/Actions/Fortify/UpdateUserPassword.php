<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Actions\Identity\ChangeUserPassword;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/**
 * Fortify adapter — change password from the profile screen.
 *
 * Requires the CURRENT password (`current_password` rule). Without that, an
 * attacker with a hijacked session could lock the real owner out by changing
 * the password without ever knowing it.
 *
 * `activateIfPending` is false here: this endpoint requires an authenticated
 * session, which a pending-activation account can never have.
 */
final class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private readonly ChangeUserPassword $changePassword) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(Authenticatable $user, array $input): void
    {
        $validated = Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ])->validateWithBag('updatePassword');

        if (! $user instanceof User) {
            return;
        }

        $this->changePassword->handle(
            user: $user,
            password: $validated['password'],
        );
    }
}
