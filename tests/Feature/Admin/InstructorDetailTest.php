<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Livewire\Admin\InstructorDetail;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('shows the instructor and lets an admin change status', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $instructor = User::factory()->instructor()->create();

    Livewire::test(InstructorDetail::class, ['instructor' => $instructor])
        ->assertSee($instructor->name)
        ->call('changeStatus', UserStatus::Suspended->value);

    expect($instructor->refresh()->status)->toBe(UserStatus::Suspended);
});

it('resends the activation link only while pending activation', function (): void {
    Notification::fake();
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $instructor = User::factory()->instructor()->awaitingActivation()->create();

    Livewire::test(InstructorDetail::class, ['instructor' => $instructor])
        ->call('resendActivation');

    Notification::assertSentTo($instructor, App\Notifications\AccountActivationNotification::class);
});

it('refuses to resend an activation link for an already-active instructor', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $instructor = User::factory()->instructor()->create(); // active by default

    Livewire::test(InstructorDetail::class, ['instructor' => $instructor])
        ->call('resendActivation')
        ->assertStatus(422);
});

it('forces a password reset', function (): void {
    Notification::fake();
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $instructor = User::factory()->instructor()->create();

    Livewire::test(InstructorDetail::class, ['instructor' => $instructor])
        ->call('forcePasswordReset');

    Notification::assertSentTo($instructor, App\Notifications\ResetPasswordNotification::class);
});

it('deletes the instructor and redirects to the index', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $instructor = User::factory()->instructor()->create();

    Livewire::test(InstructorDetail::class, ['instructor' => $instructor])
        ->call('delete')
        ->assertRedirect(route('admin.instructors.index'));

    expect(User::query()->find($instructor->id))->toBeNull();
});

it('404s when the bound user is not actually an instructor', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $student = User::factory()->student()->create();

    $this->get(route('admin.instructors.show', $student))->assertNotFound();
});

it('embeds the course-instructor-assignment component on the detail page', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $instructor = User::factory()->instructor()->create();

    // assertSeeLivewire() is a runtime macro Larastan can't see through (no
    // stub registers it on TestResponse) — assertSee() on the component's
    // wire:snapshot name is exactly what that macro checks internally.
    $this->get(route('admin.instructors.show', $instructor))
        ->assertOk()
        ->assertSee('admin.course-instructor-assignment');
});
