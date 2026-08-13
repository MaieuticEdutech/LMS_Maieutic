<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\Admin\StudentForm;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('creates a student through the form', function (): void {
    Notification::fake();
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Livewire::test(StudentForm::class)
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada@example.com')
        ->set('phone', '9999999999')
        ->call('save')
        ->assertRedirect();

    $student = User::query()->where('email', 'ada@example.com')->firstOrFail();
    expect($student->role)->toBe(UserRole::Student)
        ->and($student->name)->toBe('Ada Lovelace');
});

it('rejects an invalid or duplicate email', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $existing = User::factory()->student()->create();

    Livewire::test(StudentForm::class)
        ->set('name', 'Someone')
        ->set('email', $existing->email)
        ->call('save')
        ->assertHasErrors(['email']);
});

it('edits an existing student without touching another student\'s email uniqueness check', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $student = User::factory()->student()->create(['name' => 'Old Name']);

    Livewire::test(StudentForm::class, ['student' => $student])
        ->set('name', 'New Name')
        // Keeping the SAME email must not trip the unique-ignoring-self check.
        ->set('email', $student->email)
        ->call('save')
        ->assertRedirect(route('admin.students.show', $student->fresh()));

    expect($student->refresh()->name)->toBe('New Name');
});

it('404s when the bound user is not actually a student', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $instructor = User::factory()->instructor()->create();

    $this->get(route('admin.students.edit', $instructor))->assertNotFound();
});
