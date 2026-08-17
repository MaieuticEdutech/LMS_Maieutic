<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Terminate the session of a user whose account is no longer active.
 *
 * DEFENCE IN DEPTH, NOT THE PRIMARY CONTROL.
 * The primary check lives inside Fortify::authenticateUsing(), which prevents
 * a non-active account from ever establishing a session. This middleware
 * covers the case that check cannot: a session that was legitimately created
 * while the account was active, and the account was suspended or deactivated
 * AFTERWARDS (FR-STU-15, architecture.md §7.1.1).
 *
 * Without this, an administrator suspending an abusive student would achieve
 * nothing until that student happened to log out — their existing session
 * would keep working, possibly for hours.
 *
 * The session is invalidated and the CSRF token regenerated so the browser
 * cannot continue to act with stale credentials.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * NOT YET VERIFIED IS NOT THE SAME AS NO LONGER PERMITTED.
 *
 * Both fail canAuthenticate(), and for a while both were told the same thing:
 * "Your account is no longer active. Please contact support."
 *
 * For someone who has just registered that sentence is false in every part.
 * The account was never active, nothing has been taken away, and support
 * cannot help — the answer is in their inbox. Fortify logs a new registration
 * in directly through the guard, so the very next request landed here and
 * bounced them to a login screen showing an error about an account they had
 * created ten seconds earlier.
 *
 * So the two cases are separated. Pending verification keeps its session and
 * goes to the verification notice, which is the page that says a link has been
 * sent and offers to send another. Everything else is logged out as before.
 *
 * Keeping that session is safe and is what makes the resend button work at
 * all: `verification.notice` needs to know who is asking. The account still
 * reaches nothing — this middleware guards every real route, and the primary
 * control inside Fortify::authenticateUsing() still refuses to let such an
 * account log in through the form.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->canAuthenticate()) {
            return $next($request);
        }

        if ($user->status === UserStatus::PendingVerification) {
            if ($request->expectsJson()) {
                abort(403, 'Verify your email address to continue.');
            }

            /*
             * Safe from a redirect loop: `verification.notice` carries `web`
             * and `auth` only — never `active` — so this middleware does not
             * run on the page it sends people to.
             */
            return redirect()->route('verification.notice');
        }

        // Suspended, deactivated, or awaiting an activation link they never
        // used: access has genuinely been withdrawn or never granted, and the
        // session goes with it.
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            abort(403, 'This account is not active.');
        }

        return redirect()
            ->route('login')
            ->withErrors(['email' => __('Your account is no longer active. Please contact support.')]);
    }
}
