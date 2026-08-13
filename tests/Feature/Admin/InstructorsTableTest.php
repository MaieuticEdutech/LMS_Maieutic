<?php

declare(strict_types=1);

use App\Livewire\Admin\InstructorsTable;
use App\Models\User;
use Livewire\Livewire;

it('lists only instructors, not students or admins', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    User::factory()->instructor()->create(['name' => 'Instructor One']);
    User::factory()->student()->create(['name' => 'Student One']);

    Livewire::test(InstructorsTable::class)
        ->assertSee('Instructor One')
        ->assertDontSee('Student One');
});

it('searches by name or email', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    User::factory()->instructor()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    User::factory()->instructor()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

    Livewire::test(InstructorsTable::class)
        ->set('search', 'Ada')
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});

it('shows an empty state with no instructors', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Livewire::test(InstructorsTable::class)->assertSee('No instructors yet');
});

it('denies an instructor and a student from viewing the table', function (): void {
    // HTTP-level, not Livewire::test() — same pattern as StudentsTableTest,
    // sidesteps needing to know exactly how Livewire's test harness surfaces
    // a thrown AuthorizationException.
    $instructor = User::factory()->instructor()->create();
    $this->actingAs($instructor)->get(route('admin.instructors.index'))->assertForbidden();

    $student = User::factory()->student()->create();
    $this->actingAs($student)->get(route('admin.instructors.index'))->assertForbidden();
});
