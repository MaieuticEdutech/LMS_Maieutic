<?php

declare(strict_types=1);

use App\Enums\LessonType;
use App\Livewire\Admin\Courses\LessonEditor;
use App\Models\Lesson;
use App\Models\User;
use Livewire\Livewire;

it('updates a text lesson body', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $lesson = Lesson::factory()->ofType(LessonType::Text)->create(['title' => 'Old title']);

    Livewire::test(LessonEditor::class, ['lesson' => $lesson])
        ->set('title', 'New title')
        ->set('body', '<p>Updated content</p>')
        ->call('save');

    $lesson->refresh();

    expect($lesson->title)->toBe('New title')
        ->and($lesson->body)->toContain('Updated content');
});

it('requires a body for a text lesson', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $lesson = Lesson::factory()->ofType(LessonType::Text)->create();

    Livewire::test(LessonEditor::class, ['lesson' => $lesson])
        ->set('body', '')
        ->call('save')
        ->assertHasErrors(['body']);
});

it('requires a duration for a video lesson', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $lesson = Lesson::factory()->ofType(LessonType::Video)->create(['duration_seconds' => null]);

    Livewire::test(LessonEditor::class, ['lesson' => $lesson])
        ->set('duration_seconds', null)
        ->call('save')
        ->assertHasErrors(['duration_seconds']);
});

it('saves a video lesson duration', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $lesson = Lesson::factory()->ofType(LessonType::Video)->create();

    Livewire::test(LessonEditor::class, ['lesson' => $lesson])
        ->set('duration_seconds', 754)
        ->call('save');

    expect($lesson->refresh()->duration_seconds)->toBe(754);
});

it('can publish a lesson independently of its module', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $lesson = Lesson::factory()->ofType(LessonType::Text)->create(['is_published' => false]);

    Livewire::test(LessonEditor::class, ['lesson' => $lesson])
        ->set('is_published', true)
        ->call('save');

    expect($lesson->refresh()->is_published)->toBeTrue();
});

it('renders the type-specific editor partial for each selectable type', function (LessonType $type): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $lesson = Lesson::factory()->ofType($type)->create();

    // A render-time failure (missing view) throws before any assertion runs.
    Livewire::test(LessonEditor::class, ['lesson' => $lesson])->assertOk();
})->with([
    LessonType::Video,
    LessonType::Document,
    LessonType::Presentation,
    LessonType::Resource,
    LessonType::Text,
]);

it('denies lesson edits to an instructor', function (): void {
    $lesson = Lesson::factory()->create();
    $instructor = User::factory()->instructor()->create();

    $this->actingAs($instructor);

    Livewire::test(LessonEditor::class, ['lesson' => $lesson])
        ->call('save')
        ->assertForbidden();
});
