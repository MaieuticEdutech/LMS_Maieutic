<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| UserPolicy — the three identity invariants (AC-05, AC-06)
|--------------------------------------------------------------------------
|
| FR-RBAC-07, FR-RBAC-08, FR-RBAC-09.
|
| The Phase 4 admin UI is built on these rules. Proving them now means that
| when the screens arrive, they cannot violate an invariant by accident.
|
*/

/*
| INVARIANT 1 — only a super admin manages users.
*/
it('lets only a super admin manage users', function (string $state, bool $allowed): void {
    $actor = User::factory()->{$state}()->create();
    $target = User::factory()->student()->create();

    expect($actor->can('viewAny', User::class))->toBe($allowed)
        ->and($actor->can('create', User::class))->toBe($allowed)
        ->and($actor->can('changeRole', $target))->toBe($allowed)
        ->and($actor->can('changeStatus', $target))->toBe($allowed)
        ->and($actor->can('delete', $target))->toBe($allowed);
})->with([
    'super admin' => ['superAdmin', true],
    'instructor' => ['instructor', false],
    'student' => ['student', false],
]);

it('lets any user view and update their own record', function (string $state): void {
    $user = User::factory()->{$state}()->create();

    expect($user->can('view', $user))->toBeTrue()
        ->and($user->can('update', $user))->toBeTrue();
})->with(['superAdmin', 'instructor', 'student']);

it('stops a user viewing someone else unless they are a super admin', function (): void {
    $student = User::factory()->student()->create();
    $other = User::factory()->student()->create();

    expect($student->can('view', $other))->toBeFalse();
});

/*
| INVARIANT 2 — nobody changes their OWN role or status (FR-RBAC-08).
|
| Applies to super admins too. An attacker who compromises any account must
| not be able to escalate it from within.
*/
it('stops every role changing their own role or status', function (string $state): void {
    $user = User::factory()->{$state}()->create();

    expect($user->can('changeRole', $user))->toBeFalse()
        ->and($user->can('changeStatus', $user))->toBeFalse()
        ->and($user->can('delete', $user))->toBeFalse();
})->with(['superAdmin', 'instructor', 'student']);

it('stops a second super admin changing their own role even when others exist', function (): void {
    $admin = User::factory()->superAdmin()->create();
    User::factory()->superAdmin()->create();

    expect($admin->can('changeRole', $admin))->toBeFalse();
});

/*
| INVARIANT 3 — the last active super admin is protected (FR-RBAC-09, AC-06).
|
| Losing the final administrator means losing control of the platform with no
| in-application recovery path.
*/
it('protects the last active super admin from demotion, deactivation and deletion', function (): void {
    $only = User::factory()->superAdmin()->create();
    $other = User::factory()->superAdmin()->create();

    // With two active super admins, either may act on the other.
    expect($other->can('changeRole', $only))->toBeTrue()
        ->and($other->can('changeStatus', $only))->toBeTrue()
        ->and($other->can('delete', $only))->toBeTrue();

    // Once the other is suspended, `$only` is the last ACTIVE one.
    $other->forceFill(['status' => App\Enums\UserStatus::Suspended])->save();

    expect($other->refresh()->can('changeRole', $only->fresh()))->toBeFalse()
        ->and($other->refresh()->can('changeStatus', $only->fresh()))->toBeFalse()
        ->and($other->refresh()->can('delete', $only->fresh()))->toBeFalse();
});

it('does not count inactive super admins as a way back in', function (): void {
    $active = User::factory()->superAdmin()->create();
    $inactive = User::factory()->superAdmin()->inactive()->create();
    $second = User::factory()->superAdmin()->create();

    // `$active` is protected only when it is the last ACTIVE super admin. An
    // inactive one cannot log in, so it is no safety net.
    expect($second->can('delete', $active))->toBeTrue();

    $second->forceFill(['status' => App\Enums\UserStatus::Inactive])->save();

    expect($second->refresh()->can('delete', $active->fresh()))->toBeFalse()
        ->and($inactive->exists)->toBeTrue();
});

it('never permits permanent deletion of a user', function (): void {
    // NFR-DATA-05: user records are soft-deleted so financial and audit
    // history survives. Hard deletion is a documented manual procedure.
    $admin = User::factory()->superAdmin()->create();
    $target = User::factory()->student()->create();

    expect($admin->can('forceDelete', $target))->toBeFalse();
});

/*
| Audit log is read-only for everyone (NFR-SEC-17).
*/
it('lets only a super admin read the audit log, and nobody write it', function (string $state, bool $canRead): void {
    $actor = User::factory()->{$state}()->create();
    $entry = AuditLog::query()->create([
        'action' => 'test.event',
        'description' => 'Test entry.',
    ]);

    expect($actor->can('viewAny', AuditLog::class))->toBe($canRead)
        ->and($actor->can('view', $entry))->toBe($canRead)
        // No role may create, update or delete an entry — including a super admin.
        ->and($actor->can('create', AuditLog::class))->toBeFalse()
        ->and($actor->can('update', $entry))->toBeFalse()
        ->and($actor->can('delete', $entry))->toBeFalse();
})->with([
    'super admin' => ['superAdmin', true],
    'instructor' => ['instructor', false],
    'student' => ['student', false],
]);
