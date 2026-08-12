<?php

declare(strict_types=1);

use App\Actions\Identity\SendActivationLink;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AccountActivationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

/*
|--------------------------------------------------------------------------
| Account activation — the purchase-onboarding mechanism (AC-14)
|--------------------------------------------------------------------------
|
| FR-AUTH-05, FR-AUTH-06, FR-MAIL-02, FR-MAIL-03, FR-MAIL-04, FR-MAIL-05.
|
| Built in Phase 2, consumed by Phase 12. These tests are what make the
| payment flow safe to build later: by the time a real customer's access
| depends on this link, its single-use and expiry behaviour is already proven.
|
*/

beforeEach(function (): void {
    RateLimiter::clear('password-reset');
    RateLimiter::clear('activation-resend');
});

function issueActivationToken(User $user): string
{
    return Password::broker('activations')->createToken($user);
}

it('emails an activation link that contains no password', function (): void {
    Notification::fake();

    $user = User::factory()->awaitingActivation()->create();

    app(SendActivationLink::class)->handle($user);

    Notification::assertSentTo($user, AccountActivationNotification::class, function ($notification) use ($user) {
        $mail = $notification->toMail($user);
        $body = implode(' ', array_merge($mail->introLines, $mail->outroLines));

        // FR-MAIL-02: a raw password must NEVER be emailed. The email may
        // contain a link and instructions, but never a credential.
        expect($body)->not->toContain('password:')
            ->and($mail->actionUrl)->toContain('/activate/');

        return true;
    });

    expect(AuditLog::query()->where('action', 'user.activation_link_sent')->exists())->toBeTrue();
});

it('shows the activation form without validating the token', function (): void {
    // Validating on GET would let an attacker probe token validity, and would
    // reveal whether an address has a pending activation.
    $this->get('/activate/some-token?email=someone@example.com')
        ->assertOk()
        ->assertSee('set your password', false);
});

it('activates the account, sets the first password and marks the email verified', function (): void {
    $user = User::factory()->awaitingActivation()->create(['email' => 'buyer@example.com']);
    $token = issueActivationToken($user);

    $this->post('/activate', [
        'token' => $token,
        'email' => 'buyer@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertRedirect(route('login'));

    $user->refresh();

    expect($user->status)->toBe(UserStatus::Active)
        ->and($user->password)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();

    expect(AuditLog::query()->where('action', 'user.activated')->exists())->toBeTrue();
});

it('lets the activated user log in with the password they chose', function (): void {
    RateLimiter::clear('login');

    $user = User::factory()->awaitingActivation()->create(['email' => 'buyer@example.com']);
    $token = issueActivationToken($user);

    $this->post('/activate', [
        'token' => $token,
        'email' => 'buyer@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    $this->post('/login', [
        'email' => 'buyer@example.com',
        'password' => 'correct-horse-battery-staple',
    ]);

    $this->assertAuthenticated();
});

/*
| SINGLE USE (AC-14). The defining security property of the link. Without it,
| anyone who later obtained the email — from a shared mailbox, a backup, a
| forwarded message — could seize the account.
*/
it('refuses to reuse an activation link', function (): void {
    $user = User::factory()->awaitingActivation()->create(['email' => 'buyer@example.com']);
    $token = issueActivationToken($user);

    $payload = [
        'token' => $token,
        'email' => 'buyer@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ];

    $this->post('/activate', $payload)->assertRedirect(route('login'));

    // Second use with a DIFFERENT password: must fail, and must not change it.
    $this->post('/activate', [
        ...$payload,
        'password' => 'attacker-chosen-password',
        'password_confirmation' => 'attacker-chosen-password',
    ])->assertSessionHasErrors('email');

    RateLimiter::clear('login');
    $this->post('/login', ['email' => 'buyer@example.com', 'password' => 'attacker-chosen-password']);
    $this->assertGuest();
});

it('rejects an expired activation link', function (): void {
    $user = User::factory()->awaitingActivation()->create(['email' => 'buyer@example.com']);
    $token = issueActivationToken($user);

    // Travel past the configured activation TTL (72h by default).
    $this->travel(config()->integer('lms.auth.activation_link_ttl') + 10)->minutes();

    $this->post('/activate', [
        'token' => $token,
        'email' => 'buyer@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertSessionHasErrors('email');

    expect($user->refresh()->status)->toBe(UserStatus::PendingActivation);
});

it('rejects a forged activation token', function (): void {
    User::factory()->awaitingActivation()->create(['email' => 'buyer@example.com']);

    $this->post('/activate', [
        'token' => 'not-a-real-token',
        'email' => 'buyer@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertSessionHasErrors('email');
});

it('rejects a valid token presented with a different email', function (): void {
    $victim = User::factory()->awaitingActivation()->create(['email' => 'victim@example.com']);
    User::factory()->awaitingActivation()->create(['email' => 'attacker@example.com']);

    $token = issueActivationToken($victim);

    $this->post('/activate', [
        'token' => $token,
        'email' => 'attacker@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertSessionHasErrors('email');
});

/*
| RESEND (FR-MAIL-05) — must not become an account-enumeration oracle.
*/
it('resends an activation link to an account awaiting activation', function (): void {
    Notification::fake();

    $user = User::factory()->awaitingActivation()->create(['email' => 'buyer@example.com']);

    $this->post('/activate/resend', ['email' => 'buyer@example.com'])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, AccountActivationNotification::class);
});

it('reports the same result for an unknown address and sends nothing', function (): void {
    Notification::fake();

    $this->post('/activate/resend', ['email' => 'nobody@example.com'])
        ->assertSessionHas('status');

    Notification::assertNothingSent();
});

it('does not resend an activation link to an already active account', function (): void {
    Notification::fake();

    User::factory()->create(['email' => 'active@example.com']);

    $this->post('/activate/resend', ['email' => 'active@example.com'])
        ->assertSessionHas('status');

    Notification::assertNothingSent();
});
