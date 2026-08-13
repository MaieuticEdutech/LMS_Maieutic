<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Fortify configuration (ADR-013, architecture.md §7.1.1)
|--------------------------------------------------------------------------
|
| Phase 2 DoD: "Unused Fortify features are explicitly disabled, not left
| enabled and unused."
|
| That distinction is not pedantry. An enabled Fortify feature is a LIVE ROUTE.
| Leaving two-factor or passkeys enabled but unbuilt would expose endpoints
| nobody has designed the UI for, tested, or considered in the threat model —
| attack surface acquired by inattention.
|
*/

it('enables exactly the features the architecture specifies', function (): void {
    expect(Features::enabled(Features::registration()))->toBeTrue()
        ->and(Features::enabled(Features::resetPasswords()))->toBeTrue()
        ->and(Features::enabled(Features::emailVerification()))->toBeTrue()
        ->and(Features::enabled(Features::updatePasswords()))->toBeTrue();
});

it('disables the features the architecture excludes', function (): void {
    expect(Features::enabled(Features::updateProfileInformation()))->toBeFalse()
        ->and(Features::enabled(Features::twoFactorAuthentication()))->toBeFalse();
});

it('registers no route for a disabled feature', function (string $method, string $uri): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->call($method, $uri)
        ->assertNotFound();
})->with([
    'profile information' => ['PUT', '/user/profile-information'],
    'two-factor enable' => ['POST', '/user/two-factor-authentication'],
    'two-factor qr code' => ['GET', '/user/two-factor-qr-code'],
    'two-factor recovery codes' => ['GET', '/user/two-factor-recovery-codes'],
    'passkeys' => ['POST', '/user/passkeys'],
]);

/*
| Every Fortify screen must render an LMS view, never framework markup (C-06).
| Checking for a marker string from our own layout proves the binding is live.
*/
it('renders LMS views for every fortify screen', function (string $uri, string $heading): void {
    $this->get($uri)
        ->assertOk()
        // The layout hook is what actually proves C-06: framework markup
        // would never carry it. Asserted first because it is the claim this
        // test exists to make.
        ->assertSee('data-lms-layout="auth"', false)
        // The heading proves the RIGHT screen rendered — that /login is not
        // quietly serving the register view. Copy, so it moves when the
        // wording is deliberately changed; that is a fair trade for catching
        // a mis-wired route.
        ->assertSee($heading, false);
})->with([
    'login' => ['/login', 'Sign in'],
    'register' => ['/register', 'Create your account'],
    'forgot password' => ['/forgot-password', 'Reset your password'],
    'reset password' => ['/reset-password/some-token', 'Choose a new password'],
]);

it('uses the two configured password brokers with different lifetimes', function (): void {
    // ADR-004: reset and activation share the token table but need very
    // different windows — 60 minutes versus 72 hours.
    $reset = config()->integer('auth.passwords.users.expire');
    $activation = config()->integer('auth.passwords.activations.expire');

    expect($reset)->toBe(config()->integer('lms.auth.password_reset_ttl'))
        ->and($activation)->toBe(config()->integer('lms.auth.activation_link_ttl'))
        ->and($activation)->toBeGreaterThan($reset);
});
