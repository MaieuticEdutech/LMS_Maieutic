<?php

declare(strict_types=1);

use App\Livewire\Admin\Courses\ModuleList;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Livewire\Livewire;

it('creates a module', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $course = Course::factory()->create();

    Livewire::test(ModuleList::class, ['course' => $course])
        ->set('title', 'Getting started')
        ->call('save');

    expect($course->modules()->where('title', 'Getting started')->exists())->toBeTrue();
});

it('edits a module', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $course = Course::factory()->create();
    $module = Module::factory()->forCourse($course)->create(['title' => 'Old']);

    Livewire::test(ModuleList::class, ['course' => $course])
        ->call('openEdit', $module->id)
        ->set('title', 'New')
        ->call('save');

    expect($module->refresh()->title)->toBe('New');
});

it('deletes a module and its lessons', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $course = Course::factory()->create();
    $module = Module::factory()->forCourse($course)->create();
    Lesson::factory()->forModule($module)->create();

    Livewire::test(ModuleList::class, ['course' => $course])
        ->call('confirmDelete', $module->id)
        ->call('delete');

    expect($module->fresh())->toBeNull();
});

it('moves a module up via the keyboard-operable control', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $course = Course::factory()->create();
    $first = Module::factory()->forCourse($course)->atPosition(0)->create();
    $second = Module::factory()->forCourse($course)->atPosition(1)->create();

    Livewire::test(ModuleList::class, ['course' => $course])
        ->call('moveModule', $second->id, -1);

    expect($first->refresh()->position)->toBe(1)
        ->and($second->refresh()->position)->toBe(0);
});

it('does not move the first module further up', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $course = Course::factory()->create();
    $first = Module::factory()->forCourse($course)->atPosition(0)->create();

    Livewire::test(ModuleList::class, ['course' => $course])
        ->call('moveModule', $first->id, -1);

    expect($first->refresh()->position)->toBe(0);
});

it('rejects a reorder with an id from another course, leaving order intact', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $course = Course::factory()->create();
    $module = Module::factory()->forCourse($course)->atPosition(0)->create();
    $foreign = Module::factory()->create();

    Livewire::test(ModuleList::class, ['course' => $course])
        ->call('reorder', [$foreign->id]);

    expect($module->refresh()->position)->toBe(0);
});

it('denies module mutation to an instructor', function (): void {
    $course = Course::factory()->create();
    $instructor = User::factory()->instructor()->create();

    $this->actingAs($instructor);

    Livewire::test(ModuleList::class, ['course' => $course])
        ->call('openCreate')
        ->assertForbidden();
});
