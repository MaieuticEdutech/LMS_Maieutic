<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\ActivateAccountController;
use App\Http\Controllers\Dev\MailPreviewController;
use App\Http\Controllers\HealthController;
use App\Livewire\Catalogue\Index as CatalogueIndex;
use App\Livewire\Catalogue\Show as CatalogueShow;
use App\Services\Settings\BrandingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
|
| Routes reachable without authentication: the marketing surface, the course
| catalogue and course detail pages.
|
| ACCESS BOUNDARY (FR-RBAC-05, AC-01):
| Guests may see course METADATA ONLY — title, description, outcomes,
| requirements, instructors, curriculum titles and durations, and price.
| No lesson body, media file, downloadable resource or assessment is
| reachable from any route in this file, by any URL. There is no preview
| exemption in V1 (ADR-014); protected content lives behind routes/media.php
| and routes/student.php, both of which require an active enrollment.
|
| Phase 5 adds the catalogue and course detail pages here.
| Phase 2 adds the Fortify-backed authentication views.
|
*/

/*
 * The landing page. Organisation identity comes from BrandingService, which
 * reads the `settings` table — never from config('app.name') and never from a
 * literal in the template (rule S-1, FR-SYS-01). Renaming the organisation is
 * an administrator changing a setting, not a developer editing a view.
 */
Route::get('/', static fn (): View => view('welcome', [
    'organisation' => app(BrandingService::class)->organisationName(),
]))->name('home');

/*
|--------------------------------------------------------------------------
| Public catalogue and course detail — Phase 5 (docs/UI-GUIDE.md §3)
|--------------------------------------------------------------------------
|
| Metadata only (AC-01, ADR-014) — see the ACCESS BOUNDARY note above and
| Catalogue\Show's own docblock. No lesson body, media or assessment is
| ever reachable from either of these two routes.
*/
Route::get('/courses', CatalogueIndex::class)->name('catalogue.index');
Route::get('/courses/{course}', CatalogueShow::class)->name('catalogue.show');

/*
 * Health / readiness probe.
 *
 * Registered explicitly rather than via Application::withRouting(health: ...)
 * so it reports actual dependency reachability (database, cache, content
 * storage) instead of merely proving PHP responded. Watched by uptime
 * monitoring from Phase 17 (architecture.md §20).
 */
Route::get('/up', HealthController::class)->name('health');

/*
|--------------------------------------------------------------------------
| Account activation — LMS-owned (architecture.md §7.1.1)
|--------------------------------------------------------------------------
|
| Fortify provides login, registration, password reset, email verification and
| password updates. It has no concept of "this account was created for you and
| has never had a password", which is exactly what a purchase-created account
| is — so the LMS owns this flow.
|
| Guest-only: an activation link is meaningless to someone already signed in.
| Rate limited so the resend endpoint cannot be used to flood an inbox or to
| enumerate which addresses are awaiting activation.
|
| Built in Phase 2; the purchase flow starts issuing these links in Phase 12.
|
*/
Route::middleware('guest')->group(static function (): void {
    Route::get('/activate/{token}', [ActivateAccountController::class, 'show'])
        ->name('activate.show');

    Route::post('/activate', [ActivateAccountController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('activate.store');

    Route::post('/activate/resend', [ActivateAccountController::class, 'resend'])
        ->middleware('throttle:activation-resend')
        ->name('activate.resend');
});

/*
|--------------------------------------------------------------------------
| Mail previews — DEVELOPMENT ONLY (Phase 11)
|--------------------------------------------------------------------------
|
| Renders each transactional email in the browser so templates and branding
| can be checked without triggering the flow behind them.
|
| NOT REGISTERED IN PRODUCTION. These routes render real templates with the
| organisation's real settings; exposing them would leak branding and support
| configuration and would hand an attacker a template library for phishing
| students. The controller repeats this check in its constructor so that a
| cached route table cannot resurrect them (defence in depth, NFR-SEC-*).
|
| Enable locally with LMS_MAIL_PREVIEW_ENABLED=true.
|
*/
if (! app()->isProduction() && config()->boolean('lms.mail.preview_enabled', false)) {
    Route::prefix('dev/mail')->name('dev.mail.')->group(static function (): void {
        Route::get('/', [MailPreviewController::class, 'index'])->name('index');
        Route::get('/{email}', [MailPreviewController::class, 'show'])->name('preview');
    });
}
