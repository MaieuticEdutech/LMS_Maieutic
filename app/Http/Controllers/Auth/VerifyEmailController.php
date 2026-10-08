<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Email verification — the LMS's own route, not Fortify's.
 *
 * FORTIFY'S OWN `verification.verify` ROUTE REQUIRES AN ACTIVE SESSION
 * (`auth` middleware), because stock Fortify assumes whoever registered is
 * still logged in when they click the link. That assumption breaks the
 * moment a student checks their email on a different device than the one
 * they registered on — the single most common way anyone reads mail. They
 * would land on `auth`'s guest redirect to `/login`, and since this app's own
 * status gate (`Fortify::authenticateUsing()`) refuses to authenticate a
 * `pending_verification` account, they could never log in to begin with.
 * Verifying required being logged in; logging in required being verified.
 *
 * This route exists entirely to not have that deadlock. The signed URL
 * itself (id + a hash of the email, signed and time-limited) is already the
 * full proof of identity — nothing here needs a session on top of it, the
 * same reasoning `activate.show`/`activate.store` above already use for a
 * purchase-created account's first login.
 *
 * Deliberately NOT a `guest`-only route: unlike account activation, clicking
 * this while already logged in (the one case where Fortify's own route would
 * have worked) must keep working too.
 */
final class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::query()->find($id);

        // One generic outcome whether the id does not exist, the hash does
        // not match, or anything else is wrong — the `signed` middleware
        // already rejected a tampered or expired URL before this ever runs,
        // so reaching here with a mismatch means the link was regenerated
        // for a different address than the account now has.
        if ($user === null || ! hash_equals(sha1((string) $user->getEmailForVerification()), $hash)) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            // markEmailAsVerified() only writes the column — it does not
            // dispatch the event, that's on the caller (the same contract
            // Fortify's own VerifyEmailController follows). This is what
            // ActivateUserAfterEmailVerification listens for to turn into
            // the pending_verification -> active transition (FR-AUTH-11).
            event(new Verified($user));
        }

        // No session is created here, deliberately (FortifyServiceProvider's
        // own rule: a session exists only once authenticateUsing() has
        // checked the password AND the account status together). The user
        // verifies, then signs in like anyone else.
        return redirect()
            ->route('login')
            ->with('status', __('Your email is verified. Please sign in.'));
    }
}
