<?php

declare(strict_types=1);

use App\Actions\Catalog\CreateLesson;
use App\Actions\Catalog\CreateModule;
use App\Actions\Catalog\ReorderLessons;
use App\Actions\Catalog\ReorderModules;
use App\Enums\LessonType;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Position numbering is zero-based, and create agrees with reorder
|--------------------------------------------------------------------------
|
| The create actions numbered from 1 while the reorder actions numbered from
| 0, so a fresh course had modules at 1, 2, 3 and the first drag renumbered
| them 0, 1, 2.
|
| Nothing was visibly broken — ordering is `ORDER BY position` either way —
| which is exactly why it survived. It becomes a real bug the moment anything
| treats position 0 as "first": a move-to-top control, an import, or a test
| asserting a known layout.
|
| These assert the convention directly rather than waiting for that feature.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->course = Course::factory()->create();
});

it('gives the first module position zero', function (): void {
    $module = app(CreateModule::class)->handle($this->course, ['title' => 'First'], $this->admin);

    expect($module->position)->toBe(0);
});

it('gives the first lesson position zero', function (): void {
    $module = app(CreateModule::class)->handle($this->course, ['title' => 'M'], $this->admin);

    $lesson = app(CreateLesson::class)->handle($module, [
        'title' => 'First',
        'type' => LessonType::Text,
        'body' => '<p>x</p>',
    ], $this->admin);

    expect($lesson->position)->toBe(0);
});

it('appends each new module to the end without gaps', function (): void {
    foreach (['A', 'B', 'C'] as $title) {
        app(CreateModule::class)->handle($this->course, ['title' => $title], $this->admin);
    }

    expect($this->course->modules()->orderBy('position')->pluck('position')->all())
        ->toBe([0, 1, 2]);
});

/*
| THE POINT OF THE WHOLE EXERCISE: creating and reordering must produce the
| same numbering, so a drag does not silently renumber a course that was
| already in the right order.
*/
it('does not renumber a course when reordering into its existing order', function (): void {
    $ids = [];

    foreach (['A', 'B', 'C'] as $title) {
        $ids[] = (int) app(CreateModule::class)->handle($this->course, ['title' => $title], $this->admin)->id;
    }

    $before = $this->course->modules()->orderBy('position')->pluck('position')->all();

    // Submit the order it is already in.
    app(ReorderModules::class)->handle($this->course, $ids, $this->admin);

    expect($this->course->modules()->orderBy('position')->pluck('position')->all())->toBe($before);
});

it('does not renumber a module when reordering lessons into their existing order', function (): void {
    $module = app(CreateModule::class)->handle($this->course, ['title' => 'M'], $this->admin);

    $ids = [];

    foreach (['A', 'B', 'C'] as $title) {
        $ids[] = (int) app(CreateLesson::class)->handle($module, [
            'title' => $title,
            'type' => LessonType::Text,
            'body' => '<p>x</p>',
        ], $this->admin)->id;
    }

    $before = $module->lessons()->orderBy('position')->pluck('position')->all();

    app(ReorderLessons::class)->handle($module, $ids, $this->admin);

    expect($module->lessons()->orderBy('position')->pluck('position')->all())->toBe($before);
});

it('keeps ordering correct after a real reorder', function (): void {
    $ids = [];

    foreach (['A', 'B', 'C'] as $title) {
        $ids[] = (int) app(CreateModule::class)->handle($this->course, ['title' => $title], $this->admin)->id;
    }

    app(ReorderModules::class)->handle($this->course, array_reverse($ids), $this->admin);

    $titles = $this->course->modules()->orderBy('position')->pluck('title')->all();

    expect($titles)->toBe(['C', 'B', 'A'])
        ->and($this->course->modules()->orderBy('position')->pluck('position')->all())->toBe([0, 1, 2]);
});

it('still appends correctly to a module created before the fix', function (): void {
    // A course seeded under the old 1-based numbering must still behave: the
    // next module goes after the last one, not into a collision.
    $legacy = new Module;
    $legacy->forceFill([
        'course_id' => $this->course->id,
        'title' => 'Legacy',
        'position' => 1,
        'is_published' => true,
    ])->save();

    $next = app(CreateModule::class)->handle($this->course, ['title' => 'New'], $this->admin);

    expect($next->position)->toBe(2);
});
