<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Livewire\Admin\StudentDetail;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('shows the student and lets an admin change status', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $student = User::factory()->student()->create();

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->assertSee($student->name)
        ->call('changeStatus', UserStatus::Suspended->value);

    expect($student->refresh()->status)->toBe(UserStatus::Suspended);
});

it('resends the activation link only while pending activation', function (): void {
    Notification::fake();
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $student = User::factory()->student()->awaitingActivation()->create();

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('resendActivation');

    Notification::assertSentTo($student, App\Notifications\AccountActivationNotification::class);
});

it('refuses to resend an activation link for an already-active student', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $student = User::factory()->student()->create(); // active by default

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('resendActivation')
        ->assertStatus(422);
});

it('forces a password reset', function (): void {
    Notification::fake();
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $student = User::factory()->student()->create();

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('forcePasswordReset');

    Notification::assertSentTo($student, App\Notifications\ResetPasswordNotification::class);
});

it('deletes the student and redirects to the index', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $student = User::factory()->student()->create();

    Livewire::test(StudentDetail::class, ['student' => $student])
        ->call('delete')
        ->assertRedirect(route('admin.students.index'));

    expect(User::query()->find($student->id))->toBeNull();
});

it('404s when the bound user is not actually a student', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $instructor = User::factory()->instructor()->create();

    $this->get(route('admin.students.show', $instructor))->assertNotFound();
});
