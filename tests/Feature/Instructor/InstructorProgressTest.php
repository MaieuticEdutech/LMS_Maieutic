<?php

declare(strict_types=1);

use App\Livewire\Instructor\CoursesList;
use App\Livewire\Instructor\Dashboard;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Instructor progress surfaces (phases.md Phase 10, FR-INS-02, FR-INS-03,
| FR-INS-07) — the half of Phase 10 that waited on Phase 9's
| ProgressCalculator/lesson_progress landing on main.
|--------------------------------------------------------------------------
|
| Every figure here reads the CACHED enrollments.progress_percentage column
| (or lesson_progress.completed_at directly for the per-lesson tick), never
| recomputes it — ProgressCalculator's own correctness is Phase 9's test
| suite's job. These tests prove the instructor screens read the right rows,
| scoped to assigned courses only (AC-03).
|
*/

it('shows correct per-student and average progress on the course detail screen', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();
    $course->instructors()->attach($instructor);

    Enrollment::factory()->create(['course_id' => $course->id, 'progress_percentage' => 40]);
    Enrollment::factory()->create(['course_id' => $course->id, 'progress_percentage' => 60]);

    $this->actingAs($instructor)
        ->get(route('instructor.courses.show', $course))
        ->assertOk()
        ->assertSee('50%') // average of 40 and 60
        ->assertSee('40%')
        ->assertSee('60%');
});

it('shows the correct average progress across assigned courses on the dashboard', function (): void {
    $instructor = User::factory()->instructor()->create();
    $courseA = Course::factory()->create();
    $courseB = Course::factory()->create();
    $courseA->instructors()->attach($instructor);
    $courseB->instructors()->attach($instructor);

    Enrollment::factory()->create(['course_id' => $courseA->id, 'progress_percentage' => 20]);
    Enrollment::factory()->create(['course_id' => $courseB->id, 'progress_percentage' => 80]);

    // Not assigned — must not pull this course's enrollment into the average.
    $otherCourse = Course::factory()->create();
    Enrollment::factory()->create(['course_id' => $otherCourse->id, 'progress_percentage' => 0]);

    $this->actingAs($instructor);

    Livewire::test(Dashboard::class)
        ->assertSee('50%'); // mean of 20 and 80, excluding the unassigned course
});

it('shows per-course average progress on the assigned courses list', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create(['title' => 'Progress Overview Course']);
    $course->instructors()->attach($instructor);

    Enrollment::factory()->create(['course_id' => $course->id, 'progress_percentage' => 25]);
    Enrollment::factory()->create(['course_id' => $course->id, 'progress_percentage' => 75]);

    $this->actingAs($instructor);

    Livewire::test(CoursesList::class)
        ->assertSee('Progress Overview Course')
        ->assertSee('50% avg progress');
});

it('lets an assigned instructor drill into a student\'s per-module and per-lesson progress', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();
    $course->instructors()->attach($instructor);

    $module = Module::factory()->published()->forCourse($course)->create(['title' => 'Module One']);
    $lessonDone = Lesson::factory()->published()->forModule($module)->atPosition(0)->create(['title' => 'Finished Lesson']);
    $lessonPending = Lesson::factory()->published()->forModule($module)->atPosition(1)->create(['title' => 'Unfinished Lesson']);

    $enrollment = Enrollment::factory()->create(['course_id' => $course->id, 'progress_percentage' => 50]);

    LessonProgress::factory()->completed()->create([
        'enrollment_id' => $enrollment->id,
        'lesson_id' => $lessonDone->id,
        'user_id' => $enrollment->user_id,
    ]);

    $this->actingAs($instructor)
        ->get(route('instructor.courses.students.progress', [$course, $enrollment]))
        ->assertOk()
        ->assertSee('Module One')
        ->assertSee('Finished Lesson')
        ->assertSee('Unfinished Lesson')
        ->assertSee('1 / 2 LESSONS');
});

it('denies an unassigned instructor the course detail screen', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();

    $this->actingAs($instructor)
        ->get(route('instructor.courses.show', $course))
        ->assertNotFound();
});

it('denies an unassigned instructor a student\'s progress detail', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();
    $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

    $this->actingAs($instructor)
        ->get(route('instructor.courses.students.progress', [$course, $enrollment]))
        ->assertNotFound();
});

it('denies progress detail when the enrollment does not belong to the course in the URL', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();
    $course->instructors()->attach($instructor);

    $otherCourse = Course::factory()->create();
    $foreignEnrollment = Enrollment::factory()->create(['course_id' => $otherCourse->id]);

    $this->actingAs($instructor)
        ->get(route('instructor.courses.students.progress', [$course, $foreignEnrollment]))
        ->assertNotFound();
});

it('refuses a student or a super admin the instructor progress route entirely', function (): void {
    $course = Course::factory()->create();
    $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);

    $student = User::factory()->student()->create();
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($student)
        ->get(route('instructor.courses.students.progress', [$course, $enrollment]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('instructor.courses.students.progress', [$course, $enrollment]))
        ->assertForbidden();
});
