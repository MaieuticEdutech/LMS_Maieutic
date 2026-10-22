<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\EnrollmentSource;
use App\Enums\LessonType;
use App\Enums\ProgressStatus;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| A student can reach a course's final test (FR-STU-10, AC-31)
|--------------------------------------------------------------------------
|
| Found in an end-to-end run: the only ways into an assessment were a quiz
| lesson and the attempt history page. Nothing linked to a course-level final
| test, so a course that REQUIRES one could be taken to 100% of its lessons and
| never completed. The player now offers the way in. Whether an attempt may
| start is still StartAttempt's decision; these tests only prove the door.
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();

    $this->course = Course::factory()->published()->requiringFinalTest()->create(['title' => 'Testable Course']);
    $module = Module::factory()->forCourse($this->course)->published()->create();
    $this->lesson = Lesson::factory()->forModule($module)->published()->atPosition(0)
        ->create(['type' => LessonType::Text, 'title' => 'Only lesson', 'body' => '<p>Hello</p>']);

    $this->enrollment = app(GrantEnrollment::class)
        ->handle($this->student, $this->course, EnrollmentSource::AdminGrant, $this->admin);

    $this->finalTest = Assessment::factory()->forCourse()->create([
        'assessable_id' => $this->course->getKey(),
        'title' => 'The Final Exam',
        'is_published' => true,
    ]);

    // Opening the player already creates the row for the first lesson, so
    // finish the existing one rather than inserting a duplicate.
    $this->completeLesson = function (): void {
        $row = LessonProgress::query()
            ->where('enrollment_id', $this->enrollment->getKey())
            ->where('lesson_id', $this->lesson->getKey())
            ->first();

        if ($row !== null) {
            $row->forceFill(['status' => ProgressStatus::Completed, 'completed_at' => now()])->save();

            return;
        }

        LessonProgress::factory()->completed()->create([
            'enrollment_id' => $this->enrollment->getKey(),
            'lesson_id' => $this->lesson->getKey(),
            'user_id' => $this->student->getKey(),
        ]);
    };

    $this->player = fn () => $this->actingAs($this->student)->get(route('student.courses.play', $this->course));
});

it('links the final test from the curriculum rail with a start button', function (): void {
    ($this->player)()
        ->assertOk()
        ->assertSee('FINAL TEST')
        ->assertSee('The Final Exam')
        ->assertSee('Not attempted yet')
        ->assertSee('Start final test')
        ->assertSee(route('student.assessments.attempt', $this->finalTest), false);
});

it('does not show a final-test section for a course that has none', function (): void {
    $this->finalTest->delete();

    ($this->player)()->assertOk()->assertDontSee('FINAL TEST');
});

it('does not offer an unpublished final test', function (): void {
    $this->finalTest->forceFill(['is_published' => false])->save();

    ($this->player)()->assertOk()->assertDontSee('FINAL TEST')->assertDontSee('The Final Exam');
});

it('never shows another course\'s final test', function (): void {
    $other = Course::factory()->published()->requiringFinalTest()->create();
    Assessment::factory()->forCourse()->create([
        'assessable_id' => $other->getKey(), 'title' => 'Someone Else\'s Exam', 'is_published' => true,
    ]);

    ($this->player)()->assertOk()->assertDontSee('Someone Else\'s Exam');
});

it('shows the prominent card only once every lesson is done, for a required test', function (): void {
    ($this->player)()->assertOk()->assertDontSee('One last step: the final test');

    ($this->completeLesson)();

    ($this->player)()
        ->assertOk()
        ->assertSee('All lessons complete')
        ->assertSee('One last step: the final test')
        ->assertSee('receive your certificate');
});

it('does not show the card for a final test the course does not require', function (): void {
    $this->course->forceFill(['requires_final_test' => false])->save();
    ($this->completeLesson)();

    ($this->player)()->assertOk()->assertSee('FINAL TEST')->assertDontSee('One last step: the final test');
});

it('offers Retake and history after a failed attempt, and says how many', function (): void {
    AssessmentAttempt::factory()->create([
        'assessment_id' => $this->finalTest->getKey(),
        'user_id' => $this->student->getKey(),
        'enrollment_id' => $this->enrollment->getKey(),
        'status' => 'graded',
        'is_passed' => false,
    ]);

    ($this->player)()
        ->assertOk()
        ->assertSee('1 attempt, not passed yet')
        ->assertSee('Retake final test')
        ->assertSee(route('student.assessments.history', $this->finalTest), false);
});

it('says Passed and stops offering a new attempt once it is passed', function (): void {
    AssessmentAttempt::factory()->create([
        'assessment_id' => $this->finalTest->getKey(),
        'user_id' => $this->student->getKey(),
        'enrollment_id' => $this->enrollment->getKey(),
        'status' => 'graded',
        'is_passed' => true,
    ]);
    ($this->completeLesson)();

    ($this->player)()
        ->assertOk()
        ->assertSee('Passed')
        ->assertDontSee('Start final test')
        ->assertDontSee('Retake final test')
        ->assertDontSee('One last step: the final test');
});

it('does not count another student\'s attempts', function (): void {
    $other = User::factory()->create();
    $otherEnrollment = app(GrantEnrollment::class)->handle($other, $this->course, EnrollmentSource::AdminGrant, $this->admin);
    AssessmentAttempt::factory()->create([
        'assessment_id' => $this->finalTest->getKey(),
        'user_id' => $other->getKey(),
        'enrollment_id' => $otherEnrollment->getKey(),
        'status' => 'graded',
        'is_passed' => true,
    ]);

    ($this->player)()->assertOk()->assertSee('Not attempted yet')->assertDontSee('Passed');
});

it('the link it offers actually opens the attempt runner for an enrolled student', function (): void {
    $this->actingAs($this->student)
        ->get(route('student.assessments.attempt', $this->finalTest))
        ->assertOk();
});

it('still refuses a student who is not enrolled, whatever the player shows', function (): void {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('student.courses.play', $this->course))->assertForbidden();
    expect(Enrollment::query()->where('user_id', $stranger->getKey())->count())->toBe(0);
});
