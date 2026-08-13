<?php

declare(strict_types=1);

use App\Actions\Catalog\UpdateLesson;
use App\Actions\Catalog\UpdateModule;
use App\Actions\Enrollment\GrantEnrollment;
use App\Actions\Student\RecordLessonProgress;
use App\Enums\AssessmentType;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonType;
use App\Events\CourseCompleted;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Services\Progress\ProgressCalculator;
use App\Services\Student\StudentDashboardService;
use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| Phase 9 · Progress calculation (AC-28, AC-30, AC-31, FR-PROG-06 … 09)
|--------------------------------------------------------------------------
|
| ADR-008: the lesson row is the fact and every other figure is a cache of it.
| These tests hold that claim to account at all four levels, and — the part
| that matters most — across a curriculum change, where the denominator moves
| under enrollments that are already recorded.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();

    $this->course = Course::factory()->published()->create();
    $this->moduleOne = Module::factory()->forCourse($this->course)->published()->atPosition(0)->create();
    $this->moduleTwo = Module::factory()->forCourse($this->course)->published()->atPosition(1)->create();

    // Two modules of two lessons: four published lessons, so each is 25% of
    // the course and 50% of its module. Every figure below is readable
    // without arithmetic.
    //
    // Written out rather than looped: a plain array of four known Lessons is
    // something both a reader and the analyser can see the shape of, where an
    // append loop leaves the property typed as an empty array.
    $lesson = fn (Module $module, int $position): Lesson => Lesson::factory()
        ->forModule($module)->published()->atPosition($position)->create(['type' => LessonType::Text]);

    $this->lessons = [
        $lesson($this->moduleOne, 0),
        $lesson($this->moduleOne, 1),
        $lesson($this->moduleTwo, 0),
        $lesson($this->moduleTwo, 1),
    ];

    $this->enrollment = app(GrantEnrollment::class)
        ->handle($this->student, $this->course, EnrollmentSource::AdminGrant, $this->admin);

    $this->complete = function (Lesson $lesson): void {
        app(RecordLessonProgress::class)->handle($this->enrollment, $lesson, completed: true);
    };
});

/*
| ═══════════════ AC-28 — ALL FOUR LEVELS AGREE ═══════════════
*/
it('moves lesson, module and course figures together', function (): void {
    ($this->complete)($this->lessons[0]);

    $enrollment = $this->enrollment->refresh();
    $calculator = app(ProgressCalculator::class);

    expect($enrollment->completed_lessons_count)->toBe(1)
        ->and($enrollment->progress_percentage)->toBe(25)
        // The module holding it is half done...
        ->and($calculator->moduleProgress($this->moduleOne, $enrollment)['percentage'])->toBe(50)
        // ...and the one that does not is untouched.
        ->and($calculator->moduleProgress($this->moduleTwo, $enrollment)['percentage'])->toBe(0);
});

it('reaches a hundred per cent and records the completion', function (): void {
    array_walk($this->lessons, fn (Lesson $lesson) => ($this->complete)($lesson));

    $enrollment = $this->enrollment->refresh();

    expect($enrollment->progress_percentage)->toBe(100)
        ->and($enrollment->status)->toBe(EnrollmentStatus::Completed)
        ->and($enrollment->completed_at)->not->toBeNull();
});

it('announces course completion once, not on every later recalculation', function (): void {
    Event::fake([CourseCompleted::class]);

    array_walk($this->lessons, fn (Lesson $lesson) => ($this->complete)($lesson));

    // A recalculation that finds the course still finished must not re-fire —
    // in Phase 11 that is a second certificate email.
    app(ProgressCalculator::class)->recalculateCourse($this->enrollment->refresh());

    Event::assertDispatchedTimes(CourseCompleted::class, 1);
});

it('counts an empty course as nought per cent, never a hundred', function (): void {
    $empty = Course::factory()->published()->create();
    $enrollment = app(GrantEnrollment::class)
        ->handle($this->student, $empty, EnrollmentSource::AdminGrant, $this->admin);

    app(ProgressCalculator::class)->recalculateCourse($enrollment);

    expect($enrollment->refresh()->progress_percentage)->toBe(0)
        ->and($enrollment->completed_at)->toBeNull();
});

it('ignores progress rows whose lesson is no longer published', function (): void {
    ($this->complete)($this->lessons[0]);
    ($this->complete)($this->lessons[1]);

    // Unpublished AFTER completion. The row survives; it must stop counting,
    // or the course reads "3 of 3" against a denominator of 2.
    $this->lessons[1]->forceFill(['is_published' => false])->save();

    app(ProgressCalculator::class)->recalculateCourse($this->enrollment);

    $enrollment = $this->enrollment->refresh();

    expect($enrollment->completed_lessons_count)->toBe(1)
        ->and($enrollment->progress_percentage)->toBe(33);
});

/*
| ═══════════════ AC-30 — THE DENOMINATOR MOVES ═══════════════
*/
it('lowers every affected percentage when a lesson is published', function (): void {
    array_walk($this->lessons, fn (Lesson $lesson) => ($this->complete)($lesson));

    expect($this->enrollment->refresh()->progress_percentage)->toBe(100);

    // A fifth lesson, published through the action a course author actually
    // uses — which is what dispatches CourseStructureChanged.
    $fifth = Lesson::factory()->forModule($this->moduleTwo)->atPosition(2)
        ->create(['type' => LessonType::Text, 'is_published' => false]);

    app(UpdateLesson::class)->handle($fifth, ['is_published' => true], $this->admin);

    $enrollment = $this->enrollment->refresh();

    // Telling a student they had finished a course they had not is the
    // failure this prevents.
    expect($enrollment->progress_percentage)->toBe(80)
        ->and($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->completed_at)->toBeNull();
});

it('raises percentages again when a lesson is unpublished', function (): void {
    array_walk($this->lessons, fn (Lesson $lesson) => ($this->complete)($lesson));

    $spare = Lesson::factory()->forModule($this->moduleTwo)->published()->atPosition(2)
        ->create(['type' => LessonType::Text]);

    app(ProgressCalculator::class)->recalculateCourse($this->enrollment);
    expect($this->enrollment->refresh()->progress_percentage)->toBe(80);

    app(UpdateLesson::class)->handle($spare, ['is_published' => false], $this->admin);

    expect($this->enrollment->refresh()->progress_percentage)->toBe(100);
});

it('recalculates when a whole module is published', function (): void {
    array_walk($this->lessons, fn (Lesson $lesson) => ($this->complete)($lesson));

    $hidden = Module::factory()->forCourse($this->course)->atPosition(2)->create(['is_published' => false]);
    Lesson::factory()->forModule($hidden)->published()->atPosition(0)->create(['type' => LessonType::Text]);

    // An unpublished module hides its lessons however published they are, so
    // this changes the denominator by one.
    app(UpdateModule::class)->handle($hidden, ['is_published' => true], $this->admin);

    expect($this->enrollment->refresh()->progress_percentage)->toBe(80);
});

it('reaches every enrollment in the course, not just the one being watched', function (): void {
    $others = User::factory()->count(3)->create()->map(
        fn (User $user) => app(GrantEnrollment::class)
            ->handle($user, $this->course, EnrollmentSource::AdminGrant, $this->admin),
    );

    $others->each(function (Enrollment $enrollment): void {
        foreach ($this->lessons as $lesson) {
            app(RecordLessonProgress::class)->handle($enrollment, $lesson, completed: true);
        }
    });

    $fifth = Lesson::factory()->forModule($this->moduleTwo)->atPosition(2)
        ->create(['type' => LessonType::Text, 'is_published' => false]);

    app(UpdateLesson::class)->handle($fifth, ['is_published' => true], $this->admin);

    $others->each(fn (Enrollment $e) => expect($e->refresh()->progress_percentage)->toBe(80));
});

/*
| ═══════════════ AC-31 — THE FINAL-TEST GATE ═══════════════
*/
it('withholds completion until the final test is passed', function (): void {
    $course = Course::factory()->published()->requiringFinalTest()->create();
    $module = Module::factory()->forCourse($course)->published()->create();
    $lesson = Lesson::factory()->forModule($module)->published()->create(['type' => LessonType::Text]);

    $enrollment = app(GrantEnrollment::class)
        ->handle($this->student, $course, EnrollmentSource::AdminGrant, $this->admin);

    $test = Assessment::factory()->create([
        'assessable_type' => Course::class,
        'assessable_id' => $course->getKey(),
        'type' => AssessmentType::Test,
    ]);

    app(RecordLessonProgress::class)->handle($enrollment, $lesson, completed: true);

    // Every lesson done, and deliberately NOT complete. A certificate here
    // would certify a test the student never sat.
    expect($enrollment->refresh()->progress_percentage)->toBe(100)
        ->and($enrollment->completed_at)->toBeNull();

    AssessmentAttempt::factory()->graded()->create([
        'assessment_id' => $test->getKey(),
        'user_id' => $this->student->getKey(),
        'enrollment_id' => $enrollment->getKey(),
    ]);

    app(ProgressCalculator::class)->recalculateCourse($enrollment);

    expect($enrollment->refresh()->completed_at)->not->toBeNull();
});

it('does not accept a failed attempt as passing the final test', function (): void {
    $course = Course::factory()->published()->requiringFinalTest()->create();
    $module = Module::factory()->forCourse($course)->published()->create();
    $lesson = Lesson::factory()->forModule($module)->published()->create(['type' => LessonType::Text]);

    $enrollment = app(GrantEnrollment::class)
        ->handle($this->student, $course, EnrollmentSource::AdminGrant, $this->admin);

    $test = Assessment::factory()->create([
        'assessable_type' => Course::class,
        'assessable_id' => $course->getKey(),
        'type' => AssessmentType::Test,
    ]);

    AssessmentAttempt::factory()->graded(passed: false)->create([
        'assessment_id' => $test->getKey(),
        'user_id' => $this->student->getKey(),
        'enrollment_id' => $enrollment->getKey(),
    ]);

    app(RecordLessonProgress::class)->handle($enrollment, $lesson, completed: true);

    expect($enrollment->refresh()->completed_at)->toBeNull();
});

it('refuses to complete a course that requires a test nobody has written', function (): void {
    $course = Course::factory()->published()->requiringFinalTest()->create();
    $module = Module::factory()->forCourse($course)->published()->create();
    $lesson = Lesson::factory()->forModule($module)->published()->create(['type' => LessonType::Text]);

    $enrollment = app(GrantEnrollment::class)
        ->handle($this->student, $course, EnrollmentSource::AdminGrant, $this->admin);

    app(RecordLessonProgress::class)->handle($enrollment, $lesson, completed: true);

    // Visibly odd rather than quietly wrong: an author who forgot the test
    // will hear about it, where handing out completions would hide it.
    expect($enrollment->refresh()->progress_percentage)->toBe(100)
        ->and($enrollment->completed_at)->toBeNull();
});

it('ignores a quiz elsewhere in the course when gating completion', function (): void {
    $course = Course::factory()->published()->requiringFinalTest()->create();
    $module = Module::factory()->forCourse($course)->published()->create();
    $lesson = Lesson::factory()->forModule($module)->published()->create(['type' => LessonType::Text]);

    $enrollment = app(GrantEnrollment::class)
        ->handle($this->student, $course, EnrollmentSource::AdminGrant, $this->admin);

    // A passed lesson QUIZ is not the course's final TEST (ADR-002).
    $quiz = Assessment::factory()->create([
        'assessable_type' => Lesson::class,
        'assessable_id' => $lesson->getKey(),
        'type' => AssessmentType::Quiz,
    ]);

    AssessmentAttempt::factory()->graded()->create([
        'assessment_id' => $quiz->getKey(),
        'user_id' => $this->student->getKey(),
        'enrollment_id' => $enrollment->getKey(),
    ]);

    app(RecordLessonProgress::class)->handle($enrollment, $lesson, completed: true);

    expect($enrollment->refresh()->completed_at)->toBeNull();
});

/*
| ═══════════════ STUDENT OVERALL (FR-PROG-07) ═══════════════
*/
it('averages course percentages rather than lessons', function (): void {
    // Half of one four-lesson course.
    ($this->complete)($this->lessons[0]);
    ($this->complete)($this->lessons[1]);

    // None of a second.
    $second = Course::factory()->published()->create();
    $module = Module::factory()->forCourse($second)->published()->create();
    Lesson::factory()->forModule($module)->published()->count(10)->create(['type' => LessonType::Text]);
    app(GrantEnrollment::class)->handle($this->student, $second, EnrollmentSource::AdminGrant, $this->admin);

    $overall = app(ProgressCalculator::class)->studentOverall($this->student);

    // 50% and 0% is 25% overall — NOT 2 of 14 lessons, which is how a person
    // thinks about their own progress.
    expect($overall)->toMatchArray(['courses' => 2, 'completed' => 0, 'percentage' => 25]);
});

it('counts the same enrollments the dashboard lists', function (): void {
    // An expired enrollment the student cannot open must not drag down a
    // figure they cannot act on. The two rules are mirrored deliberately
    // (StudentDashboardService docblock); this is what stops them drifting.
    $expired = Course::factory()->published()->create();
    $enrollment = app(GrantEnrollment::class)
        ->handle($this->student, $expired, EnrollmentSource::AdminGrant, $this->admin);
    $enrollment->forceFill(['expires_at' => now()->subDay()])->save();

    $listed = app(StudentDashboardService::class)->activeEnrollments($this->student)->count();

    expect(app(ProgressCalculator::class)->studentOverall($this->student)['courses'])->toBe($listed);
});

it('reports nothing rather than nought for a student with no courses', function (): void {
    $overall = app(ProgressCalculator::class)->studentOverall(User::factory()->create());

    expect($overall)->toMatchArray(['courses' => 0, 'completed' => 0, 'percentage' => 0]);
});
