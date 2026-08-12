<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

/*
|--------------------------------------------------------------------------
| Password reset and change (FR-AUTH-04, FR-AUTH-06, FR-AUTH-12)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    RateLimiter::clear('login');
});

it('emails a reset link', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'user@example.com']);

    $this->post('/forgot-password', ['email' => 'user@example.com']);

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('resets the password with a valid token', function (): void {
    $user = User::factory()->create(['email' => 'user@example.com']);
    $token = Password::broker()->createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'user@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasNoErrors();

    $this->post('/login', ['email' => 'user@example.com', 'password' => 'a-brand-new-password']);
    $this->assertAuthenticated();

    expect(AuditLog::query()->where('action', 'user.password.changed')->exists())->toBeTrue();
});

it('refuses to reuse a reset token', function (): void {
    $user = User::factory()->create(['email' => 'user@example.com']);
    $token = Password::broker()->createToken($user);

    $payload = [
        'token' => $token,
        'email' => 'user@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ];

    $this->post('/reset-password', $payload)->assertSessionHasNoErrors();

    $this->post('/reset-password', [
        ...$payload,
        'password' => 'attacker-password',
        'password_confirmation' => 'attacker-password',
    ])->assertSessionHasErrors();

    $this->post('/login', ['email' => 'user@example.com', 'password' => 'attacker-password']);
    $this->assertGuest();
});

it('rejects an expired reset token', function (): void {
    $user = User::factory()->create(['email' => 'user@example.com']);
    $token = Password::broker()->createToken($user);

    $this->travel(config()->integer('lms.auth.password_reset_ttl') + 5)->minutes();

    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'user@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasErrors();
});

/*
| A pending-activation account whose owner uses "forgot password" instead of
| the activation link must end up ACTIVE. Otherwise they would set a password
| successfully and still be unable to log in — a dead end on the paid
| onboarding path, with no message explaining why.
*/
it('activates a pending-activation account that resets its password', function (): void {
    $user = User::factory()->awaitingActivation()->create(['email' => 'buyer@example.com']);
    $token = Password::broker()->createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'buyer@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->status)->toBe(UserStatus::Active);

    $this->post('/login', ['email' => 'buyer@example.com', 'password' => 'a-brand-new-password']);
    $this->assertAuthenticated();
});

/*
| Change password from the profile screen.
*/
it('changes the password when the current one is supplied', function (): void {
    $user = User::factory()->create(['email' => 'user@example.com']);

    $this->actingAs($user)->put('/user/password', [
        'current_password' => 'password',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasNoErrors();

    // Asserted behaviourally rather than by inspecting the hash: what matters
    // is that the NEW password authenticates and the OLD one no longer does.
    $this->post('/logout');

    $this->post('/login', ['email' => 'user@example.com', 'password' => 'password']);
    $this->assertGuest();

    RateLimiter::clear('login');
    $this->post('/login', ['email' => 'user@example.com', 'password' => 'a-brand-new-password']);
    $this->assertAuthenticated();
});

it('refuses to change the password without the current one', function (): void {
    $user = User::factory()->create(['email' => 'user@example.com']);

    $this->actingAs($user)->put('/user/password', [
        'current_password' => 'not-the-current-password',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasErrors('current_password', errorBag: 'updatePassword');

    // The original password must still work — the rejected attempt changed nothing.
    $this->post('/logout');
    $this->post('/login', ['email' => 'user@example.com', 'password' => 'password']);
    $this->assertAuthenticated();
});

it('never writes a raw password into the audit log', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/user/password', [
        'current_password' => 'password',
        'password' => 'a-very-distinctive-secret-value',
        'password_confirmation' => 'a-very-distinctive-secret-value',
    ]);

    $entries = AuditLog::query()->get()->toJson();

    expect($entries)->not->toContain('a-very-distinctive-secret-value');
});
