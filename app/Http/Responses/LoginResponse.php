<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * Send each role to its own home after login (architecture.md §7.3).
 *
 *   super_admin -> /admin
 *   instructor  -> /instructor
 *   student     -> /dashboard
 *
 * The destination comes from UserRole::homePath(), so adding or moving a role
 * home is a change in the enum rather than a search for hardcoded redirects.
 *
 * `intended()` is honoured first: a user who was bounced to login from a deep
 * link should land back there, not at their dashboard. That redirect target is
 * set by Laravel's auth middleware from the original request, and any route it
 * points at re-authorises independently — so honouring it cannot grant access
 * the user would not otherwise have.
 */
final class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        /** @var Request $request */
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $user = $request->user();

        $home = $user instanceof User
            ? $user->role->homePath()
            : '/';

        return redirect()->intended($home);
    }
}
