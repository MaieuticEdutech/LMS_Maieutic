<?php

declare(strict_types=1);

use App\Services\Settings\SettingsRepository;

/*
|--------------------------------------------------------------------------
| Phase 11 — mail previews render (development tooling)
|--------------------------------------------------------------------------
|
| The counterpart to MailSafetyTest, which proves the previews are absent in
| production. These prove they actually WORK when enabled — a preview tool that
| 500s is worse than none, because it gets ignored rather than fixed, and the
| templates stop being checked at all.
|
| The routes are registered conditionally in routes/web.php, so enabling the
| config alone is not enough inside a booted application: the route file has
| already been evaluated. These tests register the same routes explicitly and
| exercise the controller, which is where the behaviour under test lives.
|
*/

beforeEach(function (): void {
    config()->set('lms.mail.preview_enabled', true);

    app(SettingsRepository::class)->set('branding.organisation_name', 'Preview Academy', 'branding');

    Route::prefix('dev/mail')->name('dev.mail.')->group(static function (): void {
        Route::get('/', [App\Http\Controllers\Dev\MailPreviewController::class, 'index'])->name('index');
        Route::get('/{email}', [App\Http\Controllers\Dev\MailPreviewController::class, 'show'])->name('preview');
    });
});

it('lists every available preview', function (): void {
    $this->get('/dev/mail')
        ->assertOk()
        ->assertSee('verify-email')
        ->assertSee('reset-password')
        ->assertSee('account-activation')
        ->assertSee('password-changed');
});

it('renders each transactional email without error', function (string $slug): void {
    /*
     * Renders the real template through the real branding service — which is
     * the point. A template that only renders under test fixtures would not
     * catch the mistakes this tool exists to catch.
     */
    $this->get("/dev/mail/{$slug}")
        ->assertOk()
        ->assertSee('Preview Academy');
})->with([
    'verify-email',
    'reset-password',
    'account-activation',
    'password-changed',
]);

it('returns 404 for an unknown preview name', function (): void {
    $this->get('/dev/mail/no-such-email')->assertNotFound();
});

it('sends no mail while rendering a preview', function (): void {
    /*
     * A preview route that could deliver would be an open relay pointed at any
     * address a caller supplied. Previews render; they never send.
     */
    Illuminate\Support\Facades\Mail::fake();

    $this->get('/dev/mail/password-changed')->assertOk();

    Illuminate\Support\Facades\Mail::assertNothingSent();
});

it('writes no email log row for a preview', function (): void {
    // Previews are not sends, so they must not pollute the support-facing log.
    $before = App\Models\EmailLog::query()->count();

    $this->get('/dev/mail/password-changed')->assertOk();

    expect(App\Models\EmailLog::query()->count())->toBe($before);
});
