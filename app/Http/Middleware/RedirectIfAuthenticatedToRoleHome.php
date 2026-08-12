<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep already-authenticated users away from the guest-only auth screens.
 *
 * Replaces Laravel's stock RedirectIfAuthenticated so the destination is the
 * user's ROLE HOME rather than a single hardcoded path (architecture.md §7.3).
 * A logged-in super admin who opens /login should land on /admin, not on a
 * student dashboard they have no use for.
 *
 * Presentation convenience only — it grants nothing. Every destination
 * re-authorises independently.
 */
final class RedirectIfAuthenticatedToRoleHome
{
    public function handle(Request $request, Closure $next, ?string $guard = null): Response
    {
        if (Auth::guard($guard)->check()) {
            $user = $request->user();

            if ($user instanceof User) {
                return redirect($user->role->homePath());
            }
        }

        return $next($request);
    }
}
