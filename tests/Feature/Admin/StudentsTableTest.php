<?php

declare(strict_types=1);

use App\Livewire\Admin\StudentsTable;
use App\Models\User;
use Livewire\Livewire;

it('lists only students, not instructors or admins', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    User::factory()->student()->create(['name' => 'Student One']);
    User::factory()->instructor()->create(['name' => 'Instructor One']);

    Livewire::test(StudentsTable::class)
        ->assertSee('Student One')
        ->assertDontSee('Instructor One');
});

it('searches by name or email', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    User::factory()->student()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    User::factory()->student()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

    Livewire::test(StudentsTable::class)
        ->set('search', 'Ada')
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});

it('shows an empty state with no students', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Livewire::test(StudentsTable::class)->assertSee('No students yet');
});

it('denies an instructor and a student from viewing the table', function (): void {
    // HTTP-level, not Livewire::test() — the same pattern already proven in
    // AdminShellTest, and it sidesteps needing to know exactly how
    // Livewire's test harness surfaces a thrown AuthorizationException
    // (converted to a response vs. propagated as a raw exception) rather
    // than assuming.
    $instructor = User::factory()->instructor()->create();
    $this->actingAs($instructor)->get(route('admin.students.index'))->assertForbidden();

    $student = User::factory()->student()->create();
    $this->actingAs($student)->get(route('admin.students.index'))->assertForbidden();
});
