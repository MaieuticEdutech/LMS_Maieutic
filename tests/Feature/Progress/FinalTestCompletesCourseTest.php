<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Actions\Student\RecordLessonProgress;
use App\Enums\AssessmentType;
use App\Enums\EnrollmentSource;
use App\Enums\LessonType;
use App\Events\AttemptGraded;
use App\Events\CourseCompleted;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| Passing the FINAL TEST completes the course (AC-31)
|--------------------------------------------------------------------------
|
| Found in an end-to-end run: a student who finished every lesson and THEN
| passed the final test (the natural order) was never marked complete and never
| received a certificate. Completion is decided by ProgressCalculator, but it
| only ran when lesson progress asked it to; nothing asked after a test was
| passed. The existing gate test hid this by calling recalculateCourse() itself.
|
| These tests go through the real path: a graded attempt and the AttemptGraded
| event, with no manual recalculation.
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();

    $this->course = Course::factory()->published()->requiringFinalTest()->create();
    $this->module = Module::factory()->forCourse($this->course)->published()->create();
    $this->lessonOne = Lesson::factory()->forModule($this->module)->published()->atPosition(0)->create(['type' => LessonType::Text]);
    $this->lessonTwo = Lesson::factory()->forModule($this->module)->published()->atPosition(1)->create(['type' => LessonType::Text]);

    $this->enrollment = app(GrantEnrollment::class)
        ->handle($this->student, $this->course, EnrollmentSource::AdminGrant, $this->admin);

    $this->test = Assessment::factory()->create([
        'assessable_type' => Course::class,
        'assessable_id' => $this->course->getKey(),
        'type' => AssessmentType::Test,
    ]);

    $this->finishLessons = function (): void {
        foreach ([$this->lessonOne, $this->lessonTwo] as $lesson) {
            app(RecordLessonProgress::class)->handle($this->enrollment, $lesson, completed: true);
        }
    };

    // A graded attempt, announced the way SubmitAttempt announces it.
    $this->sitTest = function (bool $passed): AssessmentAttempt {
        $attempt = AssessmentAttempt::factory()->graded(passed: $passed)->create([
            'assessment_id' => $this->test->getKey(),
            'user_id' => $this->student->getKey(),
            'enrollment_id' => $this->enrollment->getKey(),
        ]);

        AttemptGraded::dispatch($attempt);

        return $attempt;
    };

    $this->fresh = fn (): Enrollment => $this->enrollment->refresh();
});

it('completes the course when the test is passed AFTER every lesson (the natural order)', function (): void {
    ($this->finishLessons)();

    // Every lesson done, test not yet sat: not complete.
    expect(($this->fresh)()->progress_percentage)->toBe(100)
        ->and(($this->fresh)()->completed_at)->toBeNull();

    ($this->sitTest)(passed: true);

    expect(($this->fresh)()->completed_at)->not->toBeNull();
});

it('announces CourseCompleted so the certificate and email follow', function (): void {
    Event::fake([CourseCompleted::class]);
    ($this->finishLessons)();

    ($this->sitTest)(passed: true);

    Event::assertDispatchedTimes(CourseCompleted::class, 1);
});

it('completes the course when the test is passed BEFORE the last lesson', function (): void {
    app(RecordLessonProgress::class)->handle($this->enrollment, $this->lessonOne, completed: true);
    ($this->sitTest)(passed: true);

    expect(($this->fresh)()->completed_at)->toBeNull();

    app(RecordLessonProgress::class)->handle($this->enrollment, $this->lessonTwo, completed: true);

    expect(($this->fresh)()->completed_at)->not->toBeNull();
});

it('does not complete the course when the final test is failed', function (): void {
    ($this->finishLessons)();

    ($this->sitTest)(passed: false);

    expect(($this->fresh)()->completed_at)->toBeNull();
});

it('does not complete the course when the test is passed but lessons are unfinished', function (): void {
    app(RecordLessonProgress::class)->handle($this->enrollment, $this->lessonOne, completed: true);

    ($this->sitTest)(passed: true);

    expect(($this->fresh)()->completed_at)->toBeNull();
});

it('fires CourseCompleted only once even if the grading event is replayed', function (): void {
    Event::fake([CourseCompleted::class]);
    ($this->finishLessons)();

    $attempt = ($this->sitTest)(passed: true);
    AttemptGraded::dispatch($attempt);
    AttemptGraded::dispatch($attempt);

    Event::assertDispatchedTimes(CourseCompleted::class, 1);
});

it('does not let a passed lesson QUIZ stand in for the final test', function (): void {
    $quizLesson = Lesson::factory()->forModule($this->module)->published()->quiz()->atPosition(2)->create();
    $quiz = Assessment::factory()->create([
        'assessable_type' => Lesson::class,
        'assessable_id' => $quizLesson->getKey(),
        'type' => AssessmentType::Quiz,
    ]);
    ($this->finishLessons)();

    $attempt = AssessmentAttempt::factory()->graded(passed: true)->create([
        'assessment_id' => $quiz->getKey(),
        'user_id' => $this->student->getKey(),
        'enrollment_id' => $this->enrollment->getKey(),
    ]);
    AttemptGraded::dispatch($attempt);

    expect(($this->fresh)()->completed_at)->toBeNull();
});
