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
         * Phase 2 registers EnsureUserIsActive and EnsureUserHasRole here.
         * Phase 14 registers the security-headers middleware (CSP, nosniff,
         * Referrer-Policy, frame-ancestors, Permissions-Policy).
         */
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
