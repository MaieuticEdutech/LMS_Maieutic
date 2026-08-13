<?php

declare(strict_types=1);

use App\Enums\LessonType;
use App\Livewire\Admin\Courses\LessonList;
use App\Livewire\Admin\Enrollments\EnrollmentsTable;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Modals opened from PHP — the payload shapes must both be handled
|--------------------------------------------------------------------------
|
| THE BUG THIS EXISTS FOR.
|
| `<x-modal>` is opened two ways, and they send DIFFERENT payload shapes:
|
|   Alpine    $dispatch('open-modal', 'x')        detail === 'x'
|   Livewire  $this->dispatch('open-modal', 'x')  detail === ['x']
|
| Livewire wraps a scalar payload in an array before it reaches the browser
| (livewire.esm.js: `if (typeof params === "string") params = [params]`).
|
| The listener compared `$event.detail === name` directly, so it matched the
| Alpine form and silently ignored the Livewire one. Everything looked
| healthy — the round-trip completed, the event fired, no dialog appeared.
|
| It was live in three places: the Course Builder's lesson Edit button, and
| both admin enrolment modals (suspend and revoke). The enrolment ones shipped
| with a green suite, which is the point of these tests.
|
| WHY THIS IS TESTABLE WHEN THE ALPINE-ORDERING BUG WAS NOT.
|
| CourseBuilderModalTest explains that Alpine does not run in Pest, so no test
| can prove a dialog opens. True — but this defect is not about timing. The
| normalisation is in the RENDERED MARKUP, so a test can assert it is present.
| That makes it a genuine regression guard rather than a note in a comment.
|
| A browser still confirmed the panel opens; these keep it from silently
| regressing afterwards.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->actingAs($this->admin);
});

/*
| ═════════════ THE NORMALISATION REACHES THE PAGE ═════════════
*/
it('renders the payload normaliser on every modal', function (): void {
    $course = Course::factory()->create();
    $module = Module::factory()->forCourse($course)->create();
    Lesson::factory()->forModule($module)->create(['type' => LessonType::Video]);

    Livewire::test(LessonList::class, ['module' => $module])
        // The helper itself...
        ->assertSee('modalTarget', escape: false)
        // ...and both listeners routed through it, rather than comparing
        // $event.detail directly.
        ->assertSee('modalTarget($event.detail)', escape: false);
});

it('never compares the raw event detail to the modal name', function (): void {
    /*
     * The exact shape of the original defect. If someone "simplifies" the
     * listener back to a direct comparison, every modal opened from PHP
     * silently stops working — and nothing else in the suite would notice.
     */
    $course = Course::factory()->create();
    $module = Module::factory()->forCourse($course)->create();
    Lesson::factory()->forModule($module)->create(['type' => LessonType::Video]);

    Livewire::test(LessonList::class, ['module' => $module])
        ->assertDontSee('$event.detail === \'', escape: false);
});

/*
| ═════════════ THE LESSON EDIT BUTTON ═════════════
*/
it('dispatches open-modal for the lesson editor', function (): void {
    $course = Course::factory()->create();
    $module = Module::factory()->forCourse($course)->create();
    $lesson = Lesson::factory()->forModule($module)->create(['type' => LessonType::Video]);

    Livewire::test(LessonList::class, ['module' => $module])
        ->call('editLesson', $lesson->id)
        ->assertDispatched('open-modal', 'lesson-editor-'.$lesson->id);
});

it('renders the lesson editor modal with a matching name', function (): void {
    /*
     * The dispatch and the listener have to agree on the name. Asserting the
     * dispatch alone would pass even if the modal were named differently —
     * which is exactly the class of bug that hides behind a green suite.
     */
    $course = Course::factory()->create();
    $module = Module::factory()->forCourse($course)->create();
    $lesson = Lesson::factory()->forModule($module)->create(['type' => LessonType::Video]);

    Livewire::test(LessonList::class, ['module' => $module])
        ->assertSee('lesson-editor-'.$lesson->id, escape: false);
});

/*
| ═════════════ THE ENROLMENT MODALS ═════════════
|
| Same defect, shipped in Phase 6's UI. Both open from PHP.
*/
it('dispatches open-modal for the enrolment lifecycle dialogs', function (string $method, string $modal): void {
    $student = User::factory()->create();
    $course = Course::factory()->create();
    $enrollment = Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
    ]);

    Livewire::test(EnrollmentsTable::class)
        ->call($method, $enrollment->id)
        ->assertDispatched('open-modal', $modal);
})->with([
    ['confirmRevoke', 'revoke-enrollment'],
    ['confirmSuspend', 'suspend-enrollment'],
]);

it('renders both enrolment modals through the normaliser', function (): void {
    $student = User::factory()->create();
    $course = Course::factory()->create();
    Enrollment::factory()->create([
        'user_id' => $student->id,
        'course_id' => $course->id,
    ]);

    Livewire::test(EnrollmentsTable::class)
        ->assertSee('revoke-enrollment', escape: false)
        ->assertSee('suspend-enrollment', escape: false)
        ->assertSee('modalTarget($event.detail)', escape: false);
});
