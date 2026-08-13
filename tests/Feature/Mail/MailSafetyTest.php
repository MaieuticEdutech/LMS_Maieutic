<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Phase 11 — no live transport, and no previews in production
|--------------------------------------------------------------------------
|
| FR-MAIL-09: "In development, email MUST default to a non-delivering driver
| (log / Mailpit) so that no real email is ever sent from a developer machine."
|
| This is a requirement about consequences, not convenience. A developer
| running a seeder against a copy of production data with a live transport
| configured sends real email to real students. It cannot be undone, and it is
| the kind of mistake that is only ever discovered afterwards.
|
*/

it('ships a non-delivering mail driver as the default', function (): void {
    /*
     * Read from the config FILE rather than the running config: the test
     * environment overrides MAIL_MAILER to `array` in phpunit.xml, so asserting
     * on the live value would prove only that the test suite is safe — not
     * that a developer's machine is (FR-MAIL-09).
     */
    $default = require base_path('config/mail.php');

    expect($default['default'])->toBeIn(['log', 'smtp', 'array'])
        ->and($default['default'])->not->toBeIn(['ses', 'ses-v2', 'postmark', 'resend', 'mailgun']);
});

it('defaults .env.example to a non-delivering driver', function (): void {
    /*
     * .env.example is what every developer copies on setup, so it is the file
     * that actually decides whether a fresh machine can send real email.
     */
    $env = (string) file_get_contents(base_path('.env.example'));

    expect($env)->toContain('MAIL_MAILER=log');
});

it('names no delivery provider in application mail config (PD-07)', function (): void {
    /*
     * The mail layer is transport-agnostic by construction: the production
     * provider is chosen in Phase 16 by setting MAIL_MAILER, with no code
     * change. A provider named in config/lms.php would be that decision
     * leaking into the application ahead of time.
     */
    $lms = require base_path('config/lms.php');
    $encoded = (string) json_encode($lms['mail']);

    expect($encoded)->not->toContain('ses')
        ->and($encoded)->not->toContain('postmark')
        ->and($encoded)->not->toContain('mailgun')
        ->and($encoded)->not->toContain('resend');
});

/*
| ═════════════ PREVIEW ROUTES ARE DEVELOPMENT-ONLY ═════════════
|
| The previews render real templates with real organisation settings. Exposed
| in production they would leak branding and support configuration, and hand an
| attacker a template library for phishing students.
*/
it('registers no mail preview route when previews are disabled', function (): void {
    config()->set('lms.mail.preview_enabled', false);

    // The suite runs with previews disabled (no LMS_MAIL_PREVIEW_ENABLED in
    // phpunit.xml), so the routes must be absent.
    expect(Route::has('dev.mail.index'))->toBeFalse()
        ->and(Route::has('dev.mail.preview'))->toBeFalse();
});

it('returns 404 from the preview endpoint when previews are disabled', function (): void {
    // Belt and braces: even by direct URL, with no registered route.
    $this->get('/dev/mail')->assertNotFound();
    $this->get('/dev/mail/password-changed')->assertNotFound();
});

it('guards the preview controller independently of route registration', function (): void {
    /*
     * Defence in depth. The route guard is the first line; this proves the
     * controller itself refuses to run when previews are disabled, so a cached
     * route table or a mistaken registration still fails closed.
     */
    config()->set('lms.mail.preview_enabled', false);

    expect(fn () => app(App\Http\Controllers\Dev\MailPreviewController::class))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});
