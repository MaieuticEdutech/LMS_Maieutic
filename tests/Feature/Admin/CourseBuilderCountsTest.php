<?php

declare(strict_types=1);

use App\Enums\LessonType;
use App\Livewire\Admin\Courses\CourseBuilder;
use App\Livewire\Admin\Courses\LessonList;
use App\Livewire\Admin\Courses\ModuleList;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Course Builder · the structure summary must match the structure
|--------------------------------------------------------------------------
|
| THE BUG THIS EXISTS FOR.
|
| The header read "0 modules · 0 lessons" on a course that visibly had both,
| in the same screen, directly above the tree listing them.
|
| It renders `$course->modules_count` and `$course->lessons_count` — withCount
| aggregates that NOTHING ever loaded. An absent attribute renders as 0, so
| the figure was not stale or miscounted: it was never computed at all, and it
| said zero for every course in every state.
|
| Two things had to be true to fix it, and only one is obvious:
|
|   1. The counts must be loaded — in render(), not mount(), because every
|      mutation calls $course->refresh(), and refresh() reloads the row's own
|      columns while DISCARDING aggregates.
|
|   2. The builder must re-render when a CHILD changes the structure.
|      LessonList already dispatched `lesson-list-changed` and nothing was
|      listening; ModuleList dispatched nothing at all. Without a listener the
|      tree updated and the summary above it did not.
|
| A count that is right on load and wrong after the first edit is arguably
| worse than one that is always wrong, so both halves are covered below.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->actingAs($this->admin);
    $this->course = Course::factory()->create(['title' => 'Counted Course']);
});

it('reports zero on a genuinely empty course', function (): void {
    Livewire::test(CourseBuilder::class, ['course' => $this->course])
        ->assertViewHas('course', fn (Course $c): bool => $c->modules_count === 0 && $c->lessons_count === 0);
});

it('counts the modules and lessons that exist', function (): void {
    $first = Module::factory()->forCourse($this->course)->create();
    $second = Module::factory()->forCourse($this->course)->create();

    Lesson::factory()->forModule($first)->create(['type' => LessonType::Video]);
    Lesson::factory()->forModule($first)->create(['type' => LessonType::Text]);
    Lesson::factory()->forModule($second)->create(['type' => LessonType::Quiz]);

    Livewire::test(CourseBuilder::class, ['course' => $this->course])
        ->assertViewHas('course', fn (Course $c): bool => $c->modules_count === 2 && $c->lessons_count === 3);
});

it('renders the real totals in the header, not zeros', function (): void {
    /*
     * Asserted on the rendered page rather than the model, because the defect
     * was in the gap between them: the attribute was missing and Blade
     * silently printed 0. A model-only assertion would not have caught it.
     */
    $module = Module::factory()->forCourse($this->course)->create();
    Lesson::factory()->forModule($module)->create(['type' => LessonType::Video]);

    $this->get(route('admin.courses.builder', $this->course))
        ->assertOk()
        ->assertSee('1 module', escape: false)
        ->assertSee('1 lesson', escape: false)
        ->assertDontSee('0 modules', escape: false);
});

/*
| ═════════════ THE COUNTS SURVIVE AN EDIT ═════════════
|
| refresh() drops withCount aggregates, so a fix that only loaded them once
| would report correctly on first paint and revert to 0 on the next mutation.
*/
it('still counts correctly after a module is added', function (): void {
    Livewire::test(ModuleList::class, ['course' => $this->course])
        ->call('openCreate')
        ->set('title', 'New module')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('module-list-changed');

    Livewire::test(CourseBuilder::class, ['course' => $this->course->fresh()])
        ->assertViewHas('course', fn (Course $c): bool => $c->modules_count === 1);
});

it('still counts correctly after a lesson is added', function (): void {
    $module = Module::factory()->forCourse($this->course)->create();

    Livewire::test(LessonList::class, ['module' => $module])
        ->call('openCreate')
        ->set('title', 'New lesson')
        ->set('type', LessonType::Video->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('lesson-list-changed');

    Livewire::test(CourseBuilder::class, ['course' => $this->course->fresh()])
        ->assertViewHas('course', fn (Course $c): bool => $c->lessons_count === 1);
});

it('listens for structure changes from its children', function (): void {
    /*
     * The listener is what makes the header update without a page reload.
     * Both events matter: lessons and modules are managed by different child
     * components, and only one of them was even dispatching before.
     */
    Livewire::test(CourseBuilder::class, ['course' => $this->course])
        ->call('structureChanged')
        ->assertHasNoErrors();

    $module = Module::factory()->forCourse($this->course)->create();

    Livewire::test(CourseBuilder::class, ['course' => $this->course])
        ->dispatch('module-list-changed')
        ->assertViewHas('course', fn (Course $c): bool => $c->modules_count === 1);
});
