<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',

        /*
         * Route files are split by audience (architecture.md §3.3, rule S-6).
         *
         * This is not cosmetic. Each audience gets its own middleware stack and
         * its own authorisation boundary, and when multi-organisation support
         * arrives in V2 a tenant-resolution middleware group wraps these files
         * unchanged rather than threading tenancy through one monolithic route
         * file (architecture.md §24.2).
         *
         * Phase 1 registers the files; later phases fill them:
         *   admin.php      -> Phase 4   (super_admin)
         *   instructor.php -> Phase 10  (instructor)
         *   student.php    -> Phase 7   (student)
         *   media.php      -> Phase 6   (protected content delivery)
         *   webhooks.php   -> Phase 12  (gateway callbacks; CSRF-exempt)
         */
        then: function (): void {
            Route::middleware('web')
                ->group(base_path('routes/admin.php'));

            Route::middleware('web')
                ->group(base_path('routes/instructor.php'));

            Route::middleware('web')
                ->group(base_path('routes/student.php'));

            Route::middleware('web')
                ->group(base_path('routes/media.php'));

            /*
             * Webhooks deliberately do NOT use the `web` middleware group:
             * no session, no cookies, and no CSRF token — a payment gateway
             * cannot present one. Their authenticity is established by
             * signature verification against the raw request body instead
             * (FR-PAY-05, FR-PAY-08, NFR-SEC-13).
             */
            Route::prefix('webhooks')
                ->name('webhooks.')
                ->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Route middleware aliases (architecture.md §8.2).
         *
         *   active — terminates a session whose user has since been suspended
         *            or deactivated. Defence in depth: the primary status check
         *            is inside Fortify::authenticateUsing(), which stops such a
         *            session being created in the first place.
         *
         *   role   — coarse gate for a route group. NOT the authority: every
         *            controller and Livewire action must still authorise the
         *            specific record through its Policy (FR-RBAC-03).
         */
        $middleware->alias([
            'active' => App\Http\Middleware\EnsureUserIsActive::class,
            'role' => App\Http\Middleware\EnsureUserHasRole::class,

            /*
             * Override the framework's `guest` alias so an already-signed-in
             * user opening /login lands on THEIR role home rather than the
             * stock single hardcoded path.
             *
             * The alias is overridden directly rather than via
             * $middleware->replace(): replace() operates on middleware groups
             * and does not rebind an alias, so the stock class stayed active
             * and every role was redirected to "/".
             */
            'guest' => App\Http\Middleware\RedirectIfAuthenticatedToRoleHome::class,
        ]);

        /*
         * Session cookie hardening (NFR-SEC-09, architecture.md §7.4).
         * Encrypted cookies are on by default in Laravel; HttpOnly, SameSite
         * and Secure are set in config/session.php.
         *
         * Phase 14 adds the security-headers middleware (CSP, nosniff,
         * Referrer-Policy, frame-ancestors, Permissions-Policy).
         */
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
