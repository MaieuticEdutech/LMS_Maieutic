<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\AccountActivationNotification;
use App\Notifications\EnrollmentGrantedNotification;
use App\Notifications\EnrollmentRevokedNotification;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Services\Settings\SettingsRepository;

/*
|--------------------------------------------------------------------------
| Phase 11 — no email hardcodes organisation identity (FR-MAIL-08, rule S-1)
|--------------------------------------------------------------------------
|
| THE SEAM THIS PROTECTS: organisation identity comes from `settings` through
| BrandingService, never from config('app.name') and never from a literal in a
| template. That is what makes per-organisation branding a configuration change
| in V2 instead of a template rewrite (architecture.md §24.2).
|
| Laravel's OWN stock mail templates hardcode config('app.name') in both the
| header and the footer, which is why resources/views/vendor/mail/ overrides
| them. These tests exist to prove that override is working and stays working —
| a `php artisan vendor:publish --force` or a framework upgrade could silently
| restore the stock version, and nothing else would notice.
|
*/

beforeEach(function (): void {
    /*
     * Written through SettingsRepository rather than the model, because the
     * repository caches and a direct model write would leave that cache stale.
     * That is also how the admin screen writes settings in Phase 4, so these
     * tests exercise the real path.
     */
    $this->settings = app(SettingsRepository::class);

    // A name that could not possibly come from config('app.name').
    $this->settings->set('branding.organisation_name', 'Distinctive Academy', 'branding');
    $this->settings->set('branding.support_email', 'help@distinctive.test', 'branding');

    $this->user = User::factory()->create(['name' => 'Test Student']);
});

/**
 * Render one transactional email by name, exactly as a recipient sees it.
 *
 * Selected by slug rather than by passing notification instances through a
 * dataset: `toMail()` is declared on each notification class, not on the
 * Notification base class, so a dataset of mixed instances has no honest
 * shared type. Naming them keeps each construction concrete.
 */
function renderMail(string $email, User $user): string
{
    $message = match ($email) {
        'verify-email' => (new VerifyEmailNotification)->toMail($user),
        'reset-password' => (new ResetPasswordNotification('token'))->toMail($user),
        'account-activation' => (new AccountActivationNotification('token'))->toMail($user),
        'password-changed' => (new PasswordChangedNotification)->toMail($user),
        'enrollment-granted' => (new EnrollmentGrantedNotification('Example Course'))->toMail($user),
        'enrollment-reactivated' => (new EnrollmentGrantedNotification('Example Course', wasReactivated: true))->toMail($user),
        'enrollment-revoked' => (new EnrollmentRevokedNotification('Example Course', 'Refund processed.'))->toMail($user),
        'enrollment-expired' => (new EnrollmentRevokedNotification('Example Course', 'Access period ended.', wasAutomatic: true))->toMail($user),
        default => throw new InvalidArgumentException("Unknown email [{$email}]."),
    };

    return (string) $message->render();
}

/**
 * Every transactional email that exists today.
 *
 * Phases 12, 8 and 9 add their mailables to this list when they build them —
 * at which point these branding guarantees cover them automatically.
 *
 * @return list<string>
 */
function transactionalEmails(): array
{
    return [
        'verify-email',
        'reset-password',
        'account-activation',
        'password-changed',
        'enrollment-granted',
        'enrollment-reactivated',
        'enrollment-revoked',
        'enrollment-expired',
    ];
}

it('renders the organisation name from settings, not config', function (string $email): void {
    expect(renderMail($email, $this->user))->toContain('Distinctive Academy');
})->with(transactionalEmails());

it('never leaks the framework app name into an email', function (string $email): void {
    /*
     * If config('app.name') appears in a rendered email, some template is
     * reading config instead of BrandingService — the exact regression
     * FR-MAIL-08 forbids, and the one Laravel's own stock templates cause.
     */
    config()->set('app.name', 'HardcodedAppName');

    expect(renderMail($email, $this->user))->not->toContain('HardcodedAppName');
})->with(transactionalEmails());

it('shows the support address from settings in the footer', function (): void {
    $html = renderMail('password-changed', $this->user);

    expect($html)->toContain('help@distinctive.test');
});

it('follows a change of organisation name without touching any template', function (): void {
    // The V2 promise in miniature: identity changes, templates do not.
    $before = renderMail('password-changed', $this->user);
    expect($before)->toContain('Distinctive Academy');

    $this->settings->set('branding.organisation_name', 'Renamed Institute', 'branding');

    $after = renderMail('password-changed', $this->user);

    expect($after)->toContain('Renamed Institute')
        ->and($after)->not->toContain('Distinctive Academy');
});

/*
| ═════════════ CONTENT RULES THAT ARE SECURITY REQUIREMENTS ═════════════
*/
it('never includes a password in the activation email (FR-MAIL-02)', function (): void {
    $html = renderMail('account-activation', $this->user);

    /*
     * FR-MAIL-02 is absolute: no raw or generated password may EVER be emailed.
     * Email sits in plaintext in mailboxes, backups and provider logs
     * indefinitely. The user always chooses their own behind a one-time link.
     */
    expect($html)->not->toContain('password:')
        ->and($html)->not->toContain('Your password is')
        ->and($html)->not->toContain('temporary password');
});

it('sends no link or token in the password-changed notice', function (): void {
    /*
     * A security notice offering a one-click "wasn't me" is an email worth
     * forging, and would let anyone reading the mailbox reverse a legitimate
     * change. The recovery path is the ordinary reset, reached by navigating
     * to the site.
     */
    $html = renderMail('password-changed', $this->user);

    expect($html)->not->toContain('/activate/')
        ->and($html)->not->toContain('/reset-password');
});
