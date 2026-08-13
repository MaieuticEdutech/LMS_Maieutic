<?php

declare(strict_types=1);

use App\Enums\LessonType;
use App\Livewire\Admin\Courses\LessonList;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Livewire\Livewire;

it('creates a text lesson', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $module = Module::factory()->create();

    Livewire::test(LessonList::class, ['module' => $module])
        ->set('title', 'Introduction')
        ->set('type', LessonType::Text->value)
        ->call('save');

    expect($module->lessons()->where('title', 'Introduction')->exists())->toBeTrue();
});

it('only offers selectable content types — never quiz', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $module = Module::factory()->create();

    Livewire::test(LessonList::class, ['module' => $module])
        ->call('openCreate')
        ->assertDontSee('Quiz');
});

it('rejects an attempt to create a quiz lesson through the form', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $module = Module::factory()->create();

    Livewire::test(LessonList::class, ['module' => $module])
        ->set('title', 'Sneaky quiz')
        ->set('type', LessonType::Quiz->value)
        ->call('save')
        ->assertHasErrors(['type']);

    expect($module->lessons()->where('title', 'Sneaky quiz')->exists())->toBeFalse();
});

it('deletes a lesson', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $module = Module::factory()->create();
    $lesson = Lesson::factory()->forModule($module)->create();

    Livewire::test(LessonList::class, ['module' => $module])
        ->call('confirmDelete', $lesson->id)
        ->call('delete');

    expect($lesson->refresh()->trashed())->toBeTrue();
});

it('moves a lesson down within its module', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $module = Module::factory()->create();
    $first = Lesson::factory()->forModule($module)->atPosition(0)->create();
    $second = Lesson::factory()->forModule($module)->atPosition(1)->create();

    Livewire::test(LessonList::class, ['module' => $module])
        ->call('moveLesson', $first->id, 1);

    expect($first->refresh()->position)->toBe(1)
        ->and($second->refresh()->position)->toBe(0);
});

it('rejects a reorder set with a duplicate id, leaving order intact', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $module = Module::factory()->create();
    $lesson = Lesson::factory()->forModule($module)->atPosition(0)->create();

    Livewire::test(LessonList::class, ['module' => $module])
        ->call('reorder', [$lesson->id, $lesson->id]);

    expect($lesson->refresh()->position)->toBe(0);
});

it('denies lesson mutation to a student', function (): void {
    $module = Module::factory()->create();
    $student = User::factory()->student()->create();

    $this->actingAs($student);

    Livewire::test(LessonList::class, ['module' => $module])
        ->call('openCreate')
        ->assertForbidden();
});
