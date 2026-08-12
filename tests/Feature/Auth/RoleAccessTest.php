<?php

declare(strict_types=1);

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Role gating and deny-by-default (FR-RBAC-02, FR-RBAC-03, FR-RBAC-10)
|--------------------------------------------------------------------------
|
| Phase 2 DoD: "Deny-by-default confirmed: an unprotected route is unreachable
| without auth."
|
| Every role area is tested against EVERY role, not just its own — the failure
| that matters is an area being reachable by the wrong role, which a test that
| only checks the happy path would never catch.
|
*/

$areas = [
    'admin' => ['/admin', 'superAdmin'],
    'instructor' => ['/instructor', 'instructor'],
    'student dashboard' => ['/dashboard', 'student'],
];

it('redirects guests to login for every protected area', function (string $path): void {
    $this->get($path)->assertRedirect('/login');
})->with([
    'admin' => '/admin',
    'instructor' => '/instructor',
    'dashboard' => '/dashboard',
    'profile' => '/profile',
]);

it('lets each role reach its own home', function (string $state, string $path): void {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get($path)->assertOk();
})->with([
    'super admin' => ['superAdmin', '/admin'],
    'instructor' => ['instructor', '/instructor'],
    'student' => ['student', '/dashboard'],
]);

/*
| CROSS-ROLE DENIAL. The important half.
*/
it('denies a student the admin and instructor areas', function (string $path): void {
    $this->actingAs(User::factory()->student()->create())
        ->get($path)
        ->assertForbidden();
})->with(['/admin', '/instructor']);

it('denies an instructor the admin and student areas', function (string $path): void {
    $this->actingAs(User::factory()->instructor()->create())
        ->get($path)
        ->assertForbidden();
})->with(['/admin', '/dashboard']);

it('denies a super admin the instructor and student areas', function (string $path): void {
    // A super admin is not exempt from role middleware. They have their own
    // area; they do not get to wander into role-specific ones by default.
    // Broader admin visibility is granted explicitly by policy in later
    // phases, never by middleware leniency.
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get($path)
        ->assertForbidden();
})->with(['/instructor', '/dashboard']);

/*
| MID-SESSION DEACTIVATION (FR-STU-15).
|
| The gap the EnsureUserIsActive middleware exists to close: a session created
| legitimately while the account was active, and the account suspended
| afterwards. Without this the suspension would achieve nothing until the user
| happened to log out.
*/
it('terminates the session of a user suspended mid-session', function (): void {
    $user = User::factory()->student()->create();

    $this->actingAs($user)->get('/dashboard')->assertOk();

    $user->forceFill(['status' => App\Enums\UserStatus::Suspended])->save();

    $this->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});

it('terminates the session of a user deactivated mid-session', function (): void {
    $user = User::factory()->instructor()->create();

    $this->actingAs($user)->get('/instructor')->assertOk();

    $user->forceFill(['status' => App\Enums\UserStatus::Inactive])->save();

    $this->get('/instructor')->assertRedirect('/login');
    $this->assertGuest();
});

/*
| Every authenticated role has a profile — it sits outside the role groups.
*/
it('gives every role access to their own profile', function (string $state): void {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get('/profile')
        ->assertOk();
})->with(['superAdmin', 'instructor', 'student']);

it('sends an authenticated user away from the guest auth screens to their role home', function (string $state, string $home): void {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get('/login')
        ->assertRedirect($home);
})->with([
    'super admin' => ['superAdmin', '/admin'],
    'instructor' => ['instructor', '/instructor'],
    'student' => ['student', '/dashboard'],
]);
