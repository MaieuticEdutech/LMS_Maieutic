<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Identity\ActivateAccount;
use App\Actions\Identity\SendActivationLink;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * First-time account activation — the LMS's own flow, not Fortify's
 * (architecture.md §7.1.1: "what Fortify does not do, and the LMS owns").
 *
 * Used by accounts created on a user's behalf: in Phase 12, by a verified
 * purchase. The user has never had a password, so there is nothing to "reset";
 * they are setting their first one.
 *
 * The token is validated by the `activations` password broker, which gives us
 * hashing at rest, expiry, single use and throttling without writing any of it
 * (ADR-004).
 */
final class ActivateAccountController extends Controller
{
    /**
     * Show the set-password form.
     *
     * The token is NOT validated here — only on submit. Validating on display
     * would let an attacker probe token validity with GET requests, and would
     * leak whether an address has a pending activation.
     */
    public function show(Request $request, string $token): View
    {
        return view('auth.activate', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /**
     * Set the password, activate the account, and sign the user in.
     */
    public function store(Request $request, ActivateAccount $activateAccount): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::default()],
        ]);

        $status = $activateAccount->handle([
            'email' => (string) $request->input('email'),
            'password' => (string) $request->input('password'),
            'password_confirmation' => (string) $request->input('password_confirmation'),
            'token' => (string) $request->input('token'),
        ]);

        if ($status !== Password::PasswordReset) {
            // One generic message for an invalid, expired or already-used
            // token. Distinguishing them would tell an attacker whether a
            // token ever existed for that address (FR-RBAC-10).
            throw ValidationException::withMessages([
                'email' => [__('This activation link is invalid or has expired. Please request a new one.')],
            ]);
        }

        return redirect()
            ->route('login')
            ->with('status', __('Your account is ready. Please sign in with your new password.'));
    }

    /**
     * Request a fresh activation link (FR-MAIL-05).
     *
     * Rate limited by the `activation-resend` limiter. Always reports success,
     * whatever the outcome: revealing that an address has no pending
     * activation would turn this endpoint into an account-enumeration oracle.
     */
    public function resend(Request $request, SendActivationLink $sendActivationLink): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::query()
            ->where('email', mb_strtolower(trim((string) $request->input('email'))))
            ->first();

        if ($user !== null && $user->awaitingActivation()) {
            $sendActivationLink->handle($user);
        }

        return back()->with('status', __('If that account is awaiting activation, a new link has been sent.'));
    }
}
