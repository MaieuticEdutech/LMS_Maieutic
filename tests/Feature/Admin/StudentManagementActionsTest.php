<?php

declare(strict_types=1);

use App\Actions\Admin\CreateStudent;
use App\Actions\Admin\DeleteUser;
use App\Actions\Admin\ForcePasswordReset;
use App\Actions\Admin\SetUserStatus;
use App\Actions\Admin\UpdateStudent;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Phase 4 Checkpoint 3 — student management Actions
|--------------------------------------------------------------------------
*/

it('creates a student with no password, pending activation, and sends the activation link', function (): void {
    Notification::fake();
    $admin = User::factory()->superAdmin()->create();

    $student = app(CreateStudent::class)->handle([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'phone' => '9999999999',
    ], $admin);

    expect($student->role)->toBe(UserRole::Student)
        ->and($student->status)->toBe(UserStatus::PendingActivation)
        ->and($student->password)->toBeNull()
        ->and(AuditLog::query()->where('action', 'user.created')->where('auditable_id', $student->id)->exists())->toBeTrue();

    Notification::assertSentTo($student, App\Notifications\AccountActivationNotification::class);
});

it('updates a student\'s own-fillable fields and audits the before/after', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->student()->create(['name' => 'Old Name', 'email' => 'old@example.com']);

    $updated = app(UpdateStudent::class)->handle($student, [
        'name' => 'New Name',
        'email' => 'new@example.com',
        'phone' => null,
    ], $admin);

    expect($updated->name)->toBe('New Name')
        ->and($updated->email)->toBe('new@example.com');

    $entry = AuditLog::query()->where('action', 'user.updated')->where('auditable_id', $student->id)->firstOrFail();
    $changes = $entry->changes ?? [];
    expect($changes['before']['name'])->toBe('Old Name')
        ->and($changes['after']['name'])->toBe('New Name');
});

it('changes status and immediately affects login eligibility', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->student()->create();

    expect($student->canAuthenticate())->toBeTrue();

    $updated = app(SetUserStatus::class)->handle($student, UserStatus::Suspended, $admin);

    expect($updated->status)->toBe(UserStatus::Suspended)
        ->and($updated->canAuthenticate())->toBeFalse();

    $entry = AuditLog::query()->where('action', 'user.status.changed')->where('auditable_id', $student->id)->firstOrFail();
    $changes = $entry->changes ?? [];
    expect($changes['before']['status'])->toBe('active')
        ->and($changes['after']['status'])->toBe('suspended');
});

it('soft deletes a user and audits it, preserving the row', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->student()->create();

    app(DeleteUser::class)->handle($student, $admin);

    expect(User::query()->find($student->id))->toBeNull()
        ->and(User::withTrashed()->find($student->id))->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'user.deleted')->where('auditable_id', $student->id)->exists())->toBeTrue();
});

it('sends a password reset link and audits it, without generating a password', function (): void {
    Notification::fake();
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->student()->create();

    app(ForcePasswordReset::class)->handle($student, $admin);

    Notification::assertSentTo($student, App\Notifications\ResetPasswordNotification::class);

    expect(AuditLog::query()->where('action', 'user.password_reset.forced')->where('auditable_id', $student->id)->exists())->toBeTrue();
});

it('never writes a raw password anywhere in the audit log for any of these actions', function (): void {
    Notification::fake();
    $admin = User::factory()->superAdmin()->create();

    // A CreateStudent-created student has NO password at all (PendingActivation
    // — architecture.md's nullable-password mechanism), so it can't prove
    // anything about a password being kept out of the log. Use a normal
    // factory student, which has a real hashed password, to make this
    // assertion actually mean something.
    $student = User::factory()->student()->create();
    $studentPasswordHash = $student->getAuthPassword();

    app(ForcePasswordReset::class)->handle($student, $admin);
    app(SetUserStatus::class)->handle($student, UserStatus::Suspended, $admin);

    $allChanges = AuditLog::query()->whereNotNull('changes')->get()->pluck('changes')->toJson();

    expect($allChanges)->not->toContain('"password":"')
        ->and($allChanges)->not->toContain($studentPasswordHash);
});
