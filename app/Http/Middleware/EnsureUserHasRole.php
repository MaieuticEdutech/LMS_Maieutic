<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coarse role gate for a route group: `->middleware('role:super_admin')`.
 *
 * THIS IS THE OUTER GATE, NOT THE AUTHORITY (architecture.md §8.2).
 * It answers "may this KIND of user be in this area?". It does NOT answer
 * "may this user touch this record?" — that is the Policy's job, and every
 * controller and Livewire action must still authorise the specific record
 * (FR-RBAC-03). Middleware alone would let an instructor reach another
 * instructor's course simply because both are instructors.
 *
 * Deny-by-default (NFR-SEC-18): an unauthenticated request is rejected before
 * any role comparison, and an unrecognised role string aborts rather than
 * being treated as a match.
 *
 * Returns 403 rather than redirecting, so a wrong-role request fails visibly
 * instead of bouncing a user around the application (FR-RBAC-10).
 */
final class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        // array_values keeps this a genuine list, which is what hasAnyRole
        // expects — not a cast to satisfy the analyser.
        $allowed = array_values(array_map(
            static fn (string $role): UserRole => UserRole::tryFrom($role)
                // A typo in a route definition must fail loudly at request
                // time, never silently widen or narrow access.
                ?? throw new InvalidArgumentException("Unknown role [{$role}] in role middleware."),
            $roles,
        ));

        if (! $user->hasAnyRole($allowed)) {
            abort(403);
        }

        return $next($request);
    }
}
