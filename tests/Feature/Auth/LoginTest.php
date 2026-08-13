<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ViewErrorBag;

/*
|--------------------------------------------------------------------------
| Login, the status gate, and rate limiting
|--------------------------------------------------------------------------
|
| FR-AUTH-02, FR-AUTH-08, FR-AUTH-09, FR-AUTH-12, AC-05.
|
| The central assertion in this file is that a non-active account NEVER
| establishes a session. It is checked with assertGuest() rather than by
| looking at the response, because the failure mode that matters is a session
| being created and then merely redirected away from — which a status check
| living only in middleware would produce (architecture.md §7.1.1).
|
*/

beforeEach(function (): void {
    // The login limiter is keyed on email+IP and persists across tests within
    // a run. Clearing it keeps each test independent (planning.md §12.3).
    RateLimiter::clear('login');
});

it('shows the login page', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertSee('data-lms-layout="auth"', false)
        ->assertSee('Sign in');
});

it('logs in an active user and records the login', function (): void {
    $user = User::factory()->create(['email' => 'active@example.com']);

    $this->post('/login', [
        'email' => 'active@example.com',
        'password' => 'password',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);

    expect($user->refresh()->last_login_at)->not->toBeNull();
    expect(AuditLog::query()->where('action', 'auth.login.succeeded')->exists())->toBeTrue();
});

it('accepts a login regardless of email casing', function (): void {
    User::factory()->create(['email' => 'active@example.com']);

    $this->post('/login', [
        'email' => 'ACTIVE@Example.COM',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
});

it('rejects a wrong password without creating a session', function (): void {
    User::factory()->create(['email' => 'active@example.com']);

    $this->post('/login', [
        'email' => 'active@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(AuditLog::query()->where('action', 'auth.login.failed')->exists())->toBeTrue();
});

/*
| THE STATUS GATE (AC-05).
|
| Each of these accounts has a VALID password. The only reason the login fails
| is the account status — and it must fail before a session exists.
*/
it('refuses to authenticate a suspended user', function (): void {
    User::factory()->suspended()->create(['email' => 'suspended@example.com']);

    $this->post('/login', [
        'email' => 'suspended@example.com',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(AuditLog::query()->where('action', 'auth.login.blocked')->exists())->toBeTrue();
});

it('refuses to authenticate an inactive user', function (): void {
    User::factory()->inactive()->create(['email' => 'inactive@example.com']);

    $this->post('/login', ['email' => 'inactive@example.com', 'password' => 'password']);

    $this->assertGuest();
});

it('refuses to authenticate an unverified user', function (): void {
    User::factory()->unverified()->create(['email' => 'unverified@example.com']);

    $this->post('/login', ['email' => 'unverified@example.com', 'password' => 'password']);

    $this->assertGuest();
});

/*
| A purchase-created account has password = NULL. No input can satisfy
| Hash::check against null, so authentication is structurally impossible —
| this is the fail-safe that protects Phase 12's onboarding flow.
*/
it('cannot authenticate an account awaiting activation under any input', function (string $password): void {
    User::factory()->awaitingActivation()->create(['email' => 'awaiting@example.com']);

    $this->post('/login', ['email' => 'awaiting@example.com', 'password' => $password]);

    $this->assertGuest();
})->with([
    'empty string' => '',
    'the word null' => 'null',
    'a plausible password' => 'password',
    'a long string' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
]);

/*
| NO USER ENUMERATION (FR-AUTH-09). Every failure — unknown account, wrong
| password, suspended, awaiting activation — must produce the SAME message,
| or the login form becomes an oracle for which accounts exist and their state.
*/
it('returns an identical generic error for every failure cause', function (): void {
    User::factory()->create(['email' => 'real@example.com']);
    User::factory()->suspended()->create(['email' => 'suspended@example.com']);

    $messages = collect([
        ['email' => 'nobody@example.com', 'password' => 'password'],
        ['email' => 'real@example.com', 'password' => 'wrong-password'],
        ['email' => 'suspended@example.com', 'password' => 'password'],
    ])->map(function (array $credentials): string {
        RateLimiter::clear('login');

        $this->post('/login', $credentials)->assertSessionHasErrors('email');

        $errors = session('errors');

        return $errors instanceof ViewErrorBag ? (string) $errors->first('email') : '';
    });

    expect($messages->unique())->toHaveCount(1)
        ->and($messages->first())->not->toBe('');
});

it('throttles repeated failed logins', function (): void {
    User::factory()->create(['email' => 'target@example.com']);

    // Fortify's limiter allows 5 per minute per email+IP.
    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['email' => 'target@example.com', 'password' => 'wrong']);
    }

    // The assertion that matters is the SECURITY OUTCOME, not the shape of the
    // rejection: once the limiter has tripped, even the CORRECT password must
    // not authenticate. Asserting on a specific status code or error bag would
    // couple this test to Fortify's presentation of the lockout rather than to
    // the behaviour we actually depend on.
    $this->post('/login', ['email' => 'target@example.com', 'password' => 'password']);

    $this->assertGuest();
});

it('allows login again once the throttle window passes', function (): void {
    User::factory()->create(['email' => 'target@example.com']);

    for ($i = 0; $i < 6; $i++) {
        $this->post('/login', ['email' => 'target@example.com', 'password' => 'wrong']);
    }

    $this->post('/login', ['email' => 'target@example.com', 'password' => 'password']);
    $this->assertGuest();

    // The lockout must be temporary. A permanent one would let an attacker
    // deny a user access to their own account indefinitely.
    $this->travel(61)->seconds();

    $this->post('/login', ['email' => 'target@example.com', 'password' => 'password']);
    $this->assertAuthenticated();
});

it('logs the user out', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect();

    $this->assertGuest();
});

/*
| Role-based landing (architecture.md §7.3).
*/
it('sends each role to its own home after login', function (string $state, string $home): void {
    RateLimiter::clear('login');

    $user = User::factory()->{$state}()->create(['email' => "{$state}@example.com"]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect($home);
})->with([
    'super admin' => ['superAdmin', '/admin'],
    'instructor' => ['instructor', '/instructor'],
    'student' => ['student', '/dashboard'],
]);
