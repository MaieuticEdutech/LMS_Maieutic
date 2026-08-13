<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\Admin\InstructorForm;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('creates an instructor through the form, including headline and bio', function (): void {
    Notification::fake();
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Livewire::test(InstructorForm::class)
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada@example.com')
        ->set('phone', '9999999999')
        ->set('headline', 'Senior Backend Engineer')
        ->set('bio', 'Builds things.')
        ->call('save')
        ->assertRedirect();

    $instructor = User::query()->where('email', 'ada@example.com')->firstOrFail();
    expect($instructor->role)->toBe(UserRole::Instructor)
        ->and($instructor->name)->toBe('Ada Lovelace')
        // ->instructorProfile() (a query), not ->instructorProfile (the magic
        // property): the latter would trip `preventLazyLoading` on a model
        // this test never eager-loaded the relation onto.
        ->and($instructor->instructorProfile()->first()?->headline)->toBe('Senior Backend Engineer')
        ->and($instructor->instructorProfile()->first()?->bio)->toBe('Builds things.');
});

it('rejects an invalid or duplicate email', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $existing = User::factory()->instructor()->create();

    Livewire::test(InstructorForm::class)
        ->set('name', 'Someone')
        ->set('email', $existing->email)
        ->call('save')
        ->assertHasErrors(['email']);
});

it('edits an existing instructor, including headline and bio, without touching another instructor\'s email uniqueness check', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $instructor = User::factory()->instructor()->create(['name' => 'Old Name']);

    Livewire::test(InstructorForm::class, ['instructor' => $instructor])
        ->set('name', 'New Name')
        // Keeping the SAME email must not trip the unique-ignoring-self check.
        ->set('email', $instructor->email)
        ->set('headline', 'Updated headline')
        ->call('save')
        ->assertRedirect(route('admin.instructors.show', $instructor->fresh()));

    expect($instructor->refresh()->name)->toBe('New Name')
        ->and($instructor->instructorProfile()->first()?->headline)->toBe('Updated headline');
});

it('404s when the bound user is not actually an instructor', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $student = User::factory()->student()->create();

    $this->get(route('admin.instructors.edit', $student))->assertNotFound();
});
