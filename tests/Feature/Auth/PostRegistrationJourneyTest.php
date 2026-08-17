<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| What a person actually sees after signing up (FR-AUTH-11)
|--------------------------------------------------------------------------
|
| ═════════════════════════════════════════════════════════════════════════
| THE STATUS GATE WAS TESTED. THE EXPERIENCE OF HITTING IT WAS NOT.
|
| Every rule here already had a unit-level test: a pending_verification
| account cannot authenticate, and EnsureUserIsActive terminates a session
| whose account stops being active. Both correct, both passing.
|
| What nobody had tested was what a human sees. Fortify logs a new
| registration straight in through the guard, so the next request hit that
| middleware and the new user was bounced to a login screen reading "Your
| account is no longer active. Please contact support." — about an account
| they had created ten seconds earlier, with no mention of the email waiting
| in their inbox.
|
| A test suite can be entirely green while the product tells its newest user
| something false. These assert the journey, not the rule.
| ═════════════════════════════════════════════════════════════════════════
|
*/

it('sends a newly registered user to the verification notice, not to an error', function (): void {
    $user = User::factory()->create(['status' => UserStatus::PendingVerification, 'email_verified_at' => null]);

    $this->actingAs($user)
        ->get(route('student.home'))
        ->assertRedirect(route('verification.notice'));
});

it('tells them a link has been sent, and offers another', function (): void {
    $user = User::factory()->create(['status' => UserStatus::PendingVerification, 'email_verified_at' => null]);

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertSee('verification link')
        // The resend button only works because the session survives the
        // redirect — the page has to know who is asking.
        ->assertSee('Resend verification email');
});

it('keeps the session alive so the resend button can work', function (): void {
    $user = User::factory()->create(['status' => UserStatus::PendingVerification, 'email_verified_at' => null]);

    $this->actingAs($user)->get(route('student.home'));

    expect(auth()->check())->toBeTrue();
});

it('never says "no longer active" to somebody who just registered', function (): void {
    $user = User::factory()->create(['status' => UserStatus::PendingVerification, 'email_verified_at' => null]);

    $response = $this->actingAs($user)->followingRedirects()->get(route('student.home'));

    // The sentence is false in every part for this user: the account was never
    // active, nothing was withdrawn, and support cannot help.
    $response->assertOk()->assertDontSee('no longer active');
});

/*
| ═══════════════ THE OTHER STATUSES ARE UNCHANGED ═══════════════
|
| The separation must not weaken the control it was carved out of.
*/
it('still terminates the session of an account that is no longer permitted', function (UserStatus $status): void {
    $user = User::factory()->create(['status' => $status]);

    $this->actingAs($user)
        ->get(route('student.home'))
        ->assertRedirect(route('login'));

    // Suspended mid-session is the case this middleware was written for, and
    // it must still end the session rather than merely redirect.
    expect(auth()->check())->toBeFalse();
})->with([
    'suspended' => UserStatus::Suspended,
    'inactive' => UserStatus::Inactive,
    'awaiting activation' => UserStatus::PendingActivation,
]);

it('still lets an active student through', function (): void {
    $user = User::factory()->student()->create(['status' => UserStatus::Active]);

    $this->actingAs($user)->get(route('student.home'))->assertOk();
});

it('refuses an unverified account as JSON without a redirect loop', function (): void {
    $user = User::factory()->create(['status' => UserStatus::PendingVerification, 'email_verified_at' => null]);

    $this->actingAs($user)
        ->getJson(route('student.home'))
        ->assertForbidden();
});

it('does not send a verified, active user to the verification notice', function (): void {
    // The redirect is keyed on the status, not on email_verified_at, so an
    // active account must pass through untouched even on this route.
    $user = User::factory()->student()->create(['status' => UserStatus::Active]);

    $this->actingAs($user)->get(route('student.home'))->assertOk();
});
