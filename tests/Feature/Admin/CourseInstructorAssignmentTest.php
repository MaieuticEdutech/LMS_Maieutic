<?php

declare(strict_types=1);

use App\Actions\Admin\AssignInstructorToCourse;
use App\Livewire\Admin\CourseInstructorAssignment;
use App\Models\Course;
use App\Models\User;
use App\Policies\CoursePolicy;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| THE MOST IMPORTANT TEST IN THIS CHECKPOINT
|--------------------------------------------------------------------------
|
| Proves that assigning an instructor to a course is not merely "a row now
| exists in course_instructor" — it is what CoursePolicy itself keys off,
| via Course::isAssignedTo(). If a future change ever made the assignment
| screen write to the pivot table through any path OTHER than
| AssignInstructorToCourse / Course::instructors(), this test would still
| pass or fail on the thing that actually matters: does the instructor's
| access change.
|
*/

it('assigning an instructor to a course flips isAssignedTo from false to true, and CoursePolicy authorisation keys off exactly that', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();
    $policy = app(CoursePolicy::class);

    // BEFORE: no pivot row, no access, no authorisation.
    expect($course->isAssignedTo($instructor))->toBeFalse()
        ->and(DB::table('course_instructor')->where('course_id', $course->id)->where('user_id', $instructor->id)->exists())->toBeFalse()
        ->and($policy->viewStudents($instructor, $course))->toBeFalse()
        ->and($policy->manageAssessments($instructor, $course))->toBeFalse()
        ->and($policy->view($instructor, $course))->toBeFalse(); // course is a draft, not published

    app(AssignInstructorToCourse::class)->handle($course, $instructor, $admin);
    $course->refresh();

    // AFTER: the pivot row exists AND, more importantly, every policy method
    // that gates on assignment now allows it — the assignment is what the
    // authorisation decision actually rests on, not an incidental row.
    expect($course->isAssignedTo($instructor))->toBeTrue()
        ->and(DB::table('course_instructor')->where('course_id', $course->id)->where('user_id', $instructor->id)->exists())->toBeTrue()
        ->and($policy->viewStudents($instructor, $course))->toBeTrue()
        ->and($policy->manageAssessments($instructor, $course))->toBeTrue()
        ->and($policy->view($instructor, $course))->toBeTrue();
});

it('assigns and unassigns through the Livewire component, driving the same isAssignedTo state', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create(['title' => 'Introduction to Testing']);

    expect($course->isAssignedTo($instructor))->toBeFalse();

    Livewire::test(CourseInstructorAssignment::class, ['instructor' => $instructor])
        ->set('courseToAssign', (string) $course->id)
        ->call('assign')
        ->assertSee('Introduction to Testing');

    expect($course->refresh()->isAssignedTo($instructor))->toBeTrue();

    Livewire::test(CourseInstructorAssignment::class, ['instructor' => $instructor])
        ->call('unassign', $course->id)
        ->assertSee('Not assigned to any courses yet.');

    expect($course->refresh()->isAssignedTo($instructor))->toBeFalse();
});

it('only lists courses NOT yet assigned in the assign control, and only assigned courses in the assigned list', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $instructor = User::factory()->instructor()->create();
    $assignedCourse = Course::factory()->create(['title' => 'Already Assigned Course']);
    $unassignedCourse = Course::factory()->create(['title' => 'Not Yet Assigned Course']);

    app(AssignInstructorToCourse::class)->handle($assignedCourse, $instructor, $admin);

    $html = Livewire::test(CourseInstructorAssignment::class, ['instructor' => $instructor])->html();

    // The assigned course shows in the "assigned" list (with an Unassign
    // button) AND must NOT appear again as an option in the assign <select>
    // — it is one course in one of the two lists, never both. Checked against
    // the actual <option>…</option> rendering rather than a raw occurrence
    // count: the assigned course's title legitimately appears a second time
    // in the Unassign button's wire:confirm text, so counting substrings
    // would fail on entirely correct markup.
    expect($html)->toContain('Already Assigned Course')
        ->and($html)->toContain('>Not Yet Assigned Course</option>')
        ->and($html)->not->toContain('>Already Assigned Course</option>');
});

it('denies assign/unassign to a non-super-admin who is not assigned to the course', function (): void {
    $otherInstructor = User::factory()->instructor()->create();
    $this->actingAs($otherInstructor);
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();

    Livewire::test(CourseInstructorAssignment::class, ['instructor' => $instructor])
        ->set('courseToAssign', (string) $course->id)
        ->call('assign')
        ->assertForbidden();
});
