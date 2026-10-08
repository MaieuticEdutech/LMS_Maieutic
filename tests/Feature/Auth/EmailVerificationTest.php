<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/*
|--------------------------------------------------------------------------
| Email verification (FR-AUTH-11, architecture.md §7.2)
|--------------------------------------------------------------------------
*/

function verificationUrlFor(User $user): string
{
    return URL::temporarySignedRoute('verification.verify.lms', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1((string) $user->getEmailForVerification()),
    ]);
}

it('promotes a pending_verification account to active on verification as a guest', function (): void {
    // THE CASE FORTIFY'S OWN ROUTE COULD NEVER HANDLE: no session at all.
    // Registering on one device and checking mail on another is the normal
    // case, not the edge case — this must work logged out.
    $user = User::factory()->unverified()->create();

    $this->get(verificationUrlFor($user))->assertRedirect(route('login'));

    $user->refresh();

    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->status)->toBe(UserStatus::Active);

    expect(AuditLog::query()->where('action', 'user.email.verified')->exists())->toBeTrue();
});

it('also verifies when the link is opened in the same session that registered', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(verificationUrlFor($user));

    expect($user->refresh()->status)->toBe(UserStatus::Active);
});

it('rejects a verification link with a tampered hash', function (): void {
    $user = User::factory()->unverified()->create();

    $bad = URL::temporarySignedRoute('verification.verify.lms', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1('someone.else@example.com'),
    ]);

    $this->get($bad)->assertForbidden();

    expect($user->refresh()->email_verified_at)->toBeNull();
});

it('rejects an expired verification link', function (): void {
    $user = User::factory()->unverified()->create();
    $url = verificationUrlFor($user);

    $this->travel(70)->minutes();

    $this->get($url)->assertForbidden();

    expect($user->refresh()->email_verified_at)->toBeNull();
});

/*
| DELIBERATELY NARROW LISTENER.
|
| A verification event must NOT reactivate a suspended account. If it did, a
| suspended user could restore their own access simply by re-verifying their
| address — turning an email click into a privilege change.
*/
it('does not reactivate a suspended account through email verification', function (): void {
    $user = User::factory()->suspended()->create(['email_verified_at' => null]);

    $this->get(verificationUrlFor($user));

    expect($user->refresh()->status)->toBe(UserStatus::Suspended);
});

it('does not reactivate an inactive account through email verification', function (): void {
    $user = User::factory()->inactive()->create(['email_verified_at' => null]);

    $this->get(verificationUrlFor($user));

    expect($user->refresh()->status)->toBe(UserStatus::Inactive);
});
