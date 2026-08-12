<?php

declare(strict_types=1);

namespace App\Http\Middleware;

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
 */
final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->canAuthenticate()) {
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

        return $next($request);
    }
}
