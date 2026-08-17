<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

/**
 * Send a signed-out user to the login screen (FR-AUTH-07).
 *
 * Fortify's default lands them on `/` — the public homepage. That is the right
 * behaviour for a consumer site someone might browse after signing out, but it
 * is the wrong one here: everybody who signs out of this application is a
 * learner, instructor or administrator finishing a session, and the next thing
 * they will want is the way back in. Bouncing them to marketing copy makes them
 * hunt for "Sign in" to do the obvious next thing.
 *
 * WHY THE CONFIRMATION MESSAGE
 *
 * Arriving at a login form with no explanation is ambiguous — it reads the same
 * as a session that expired, or a click that failed. Saying so out loud is the
 * difference between "I signed out" and "something logged me out".
 *
 * Flashing here is safe despite the session having just been invalidated:
 * Fortify calls invalidate() and regenerateToken() in the controller and
 * resolves this response afterwards, so the flash is written to the regenerated
 * session and survives the redirect.
 *
 * WHY NOT config('fortify.redirects.logout')
 *
 * That key would set the destination, but it cannot carry the message — and a
 * second place where auth redirects are decided is exactly the kind of split
 * that leaves someone reading FortifyServiceProvider convinced they have seen
 * all of them. Both responses are bound in one register() method instead.
 */
final class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        /** @var Request $request */

        // An API client gets the same 204 Fortify would have sent. A redirect
        // to an HTML login form is meaningless to it.
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return redirect()
            ->route('login')
            ->with('status', 'You have been signed out.');
    }
}
