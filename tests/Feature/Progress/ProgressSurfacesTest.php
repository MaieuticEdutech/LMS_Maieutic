<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Actions\Student\RecordLessonProgress;
use App\Enums\AssessmentType;
use App\Enums\CompletionSource;
use App\Enums\EnrollmentSource;
use App\Enums\LessonType;
use App\Enums\ProgressStatus;
use App\Events\AttemptGraded;
use App\Livewire\Student\CoursePlayer;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\MediaFile;
use App\Models\Module;
use App\Models\User;
use App\Services\Progress\ProgressCalculator;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Phase 9 · Progress on screen (FR-PROG-04, FR-PROG-07, AC-29, NFR-PERF-04)
|--------------------------------------------------------------------------
|
| The engine is tested elsewhere. What matters here is that a student can SEE
| where they are, that the control they are offered is the one their lesson
| actually honours, and that none of it costs a query per course.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();

    $this->course = Course::factory()->published()->create();
    $this->module = Module::factory()->forCourse($this->course)->published()->create();

    $this->text = Lesson::factory()->forModule($this->module)->published()->atPosition(0)
        ->create(['type' => LessonType::Text, 'title' => 'Reading one']);
    $this->video = Lesson::factory()->forModule($this->module)->published()->atPosition(1)
        ->create(['type' => LessonType::Video, 'title' => 'Watching one', 'duration_seconds' => 100]);
    $this->quiz = Lesson::factory()->forModule($this->module)->published()->atPosition(2)
        ->create(['type' => LessonType::Quiz, 'title' => 'Quizzing one']);

    $this->enrollment = app(GrantEnrollment::class)
        ->handle($this->student, $this->course, EnrollmentSource::AdminGrant, $this->admin);
});

/*
| ═══════════════ THE CONTROL MATCHES THE RULE (FR-PROG-04) ═══════════════
*/
it('offers mark-as-complete only on a lesson that honours it', function (): void {
    $this->actingAs($this->student);

    Livewire::test(CoursePlayer::class, ['course' => $this->course, 'lesson' => $this->text])
        ->assertSee('Mark as complete');

    // A button the server would refuse reads as broken, not as a rule.
    Livewire::test(CoursePlayer::class, ['course' => $this->course, 'lesson' => $this->video])
        ->assertDontSee('Mark as complete')
        ->assertSee('completes once you have watched');

    Livewire::test(CoursePlayer::class, ['course' => $this->course, 'lesson' => $this->quiz])
        ->assertDontSee('Mark as complete')
        ->assertSee('completes when you pass its assessment');
});

it('refuses a manual completion call on a video even if one is forged', function (): void {
    $this->actingAs($this->student);

    // Hiding the control is presentation. This is the check (Rule 20).
    Livewire::test(CoursePlayer::class, ['course' => $this->course, 'lesson' => $this->video])
        ->call('toggleComplete');

    expect(LessonProgress::query()->where('lesson_id', $this->video->getKey())->first()?->completed_at)
        ->toBeNull();
});

it('lets a student undo a manual completion they did not mean', function (): void {
    $this->actingAs($this->student);

    Livewire::test(CoursePlayer::class, ['course' => $this->course, 'lesson' => $this->text])
        ->call('toggleComplete')
        ->assertSee('Completed')
        ->call('toggleComplete')
        ->assertSee('Mark as complete');
});

it('does not accept a completion claim from the browser on a video', function (): void {
    $this->actingAs($this->student);

    // recordProgress reports a POSITION and nothing else. A client that could
    // assert completion would make the threshold decoration.
    Livewire::test(CoursePlayer::class, ['course' => $this->course, 'lesson' => $this->video])
        ->call('recordProgress', 10);

    expect(LessonProgress::query()->where('lesson_id', $this->video->getKey())->first()?->completed_at)
        ->toBeNull();
});

/*
| ═══════════════ THE FIGURES ARE VISIBLE ═══════════════
*/
it('shows course and module progress in the player', function (): void {
    app(RecordLessonProgress::class)->handle($this->enrollment, $this->text, completed: true);

    $this->actingAs($this->student);

    Livewire::test(CoursePlayer::class, ['course' => $this->course, 'lesson' => $this->text])
        ->assertSee('1 / 3 COMPLETE')
        ->assertSee('33%')
        ->assertSee('1 / 3 LESSONS');
});

it('shows how much of a video counts as watched so far', function (): void {
    MediaFile::factory()->video()->attachedTo($this->video)->create();

    app(RecordLessonProgress::class)->handle($this->enrollment, $this->video, positionSeconds: 40);

    $this->actingAs($this->student);

    // Without this a student who stops at 85% has no way to know why the
    // lesson did not tick, and "it just doesn't work" is the fair conclusion.
    Livewire::test(CoursePlayer::class, ['course' => $this->course, 'lesson' => $this->video])
        ->assertSee('40% WATCHED')
        ->assertSee('COMPLETES AT 90%');
});

it('marks the course complete on screen only once it really is', function (): void {
    $this->actingAs($this->student);

    Livewire::test(CoursePlayer::class, ['course' => $this->course, 'lesson' => $this->text])
        ->assertDontSee('Course complete');

    // Written directly rather than through the action: the video and quiz
    // have rules of their own, and what is under test here is the surface,
    // not how each lesson came to be finished.
    collect([$this->text, $this->video, $this->quiz])->each(function (Lesson $lesson): void {
        // The mount above already opened the first lesson, so a row may exist.
        $progress = LessonProgress::query()
            ->where('enrollment_id', $this->enrollment->getKey())
            ->where('lesson_id', $lesson->getKey())
            ->first() ?? new LessonProgress;

        $progress->forceFill([
            'enrollment_id' => $this->enrollment->getKey(),
            'lesson_id' => $lesson->getKey(),
            'user_id' => $this->student->getKey(),
            'status' => ProgressStatus::Completed,
            'completed_at' => now(),
            'completion_source' => CompletionSource::Manual,
            'first_accessed_at' => now(),
        ])->save();
    });

    app(ProgressCalculator::class)->recalculateCourse($this->enrollment);

    Livewire::test(CoursePlayer::class, ['course' => $this->course, 'lesson' => $this->text])
        ->assertSee('Course complete');
});

/*
| ═══════════════ AC-29 — CONTINUE LEARNING ═══════════════
*/
it('resumes at the exact lesson the student last opened', function (): void {
    $this->actingAs($this->student)
        ->get(route('student.courses.play', [$this->course, $this->video]))
        ->assertOk();

    // No lesson in the URL means "resume".
    $this->actingAs($this->student)
        ->get(route('student.courses.play', $this->course))
        ->assertOk()
        ->assertSee('Watching one');
});

it('falls back to the first lesson when the last one is gone', function (): void {
    $this->actingAs($this->student)
        ->get(route('student.courses.play', [$this->course, $this->video]))
        ->assertOk();

    $this->video->forceFill(['is_published' => false])->save();

    // A dead end on "continue learning" is worse than starting over.
    $this->actingAs($this->student)
        ->get(route('student.courses.play', $this->course))
        ->assertOk()
        ->assertSee('Reading one');
});

it('shows overall progress on the dashboard', function (): void {
    app(RecordLessonProgress::class)->handle($this->enrollment, $this->text, completed: true);

    $this->actingAs($this->student)
        ->get(route('student.home'))
        ->assertOk()
        ->assertSee('Overall progress')
        ->assertSee('33%');
});

/*
| ═══════════════ QUIZ-DRIVEN COMPLETION (FR-PROG-04) ═══════════════
*/
it('completes a quiz lesson when its assessment is passed', function (): void {
    $assessment = Assessment::factory()->create([
        'assessable_type' => Lesson::class,
        'assessable_id' => $this->quiz->getKey(),
        'type' => AssessmentType::Quiz,
    ]);

    $attempt = AssessmentAttempt::factory()->graded()->create([
        'assessment_id' => $assessment->getKey(),
        'user_id' => $this->student->getKey(),
        'enrollment_id' => $this->enrollment->getKey(),
    ]);

    AttemptGraded::dispatch($attempt);

    $progress = LessonProgress::query()->where('lesson_id', $this->quiz->getKey())->first();

    expect($progress?->completed_at)->not->toBeNull()
        ->and($progress?->completion_source)->toBe(CompletionSource::Assessment);
});

it('leaves the lesson unfinished when the attempt failed', function (): void {
    $assessment = Assessment::factory()->create([
        'assessable_type' => Lesson::class,
        'assessable_id' => $this->quiz->getKey(),
        'type' => AssessmentType::Quiz,
    ]);

    $attempt = AssessmentAttempt::factory()->graded(passed: false)->create([
        'assessment_id' => $assessment->getKey(),
        'user_id' => $this->student->getKey(),
        'enrollment_id' => $this->enrollment->getKey(),
    ]);

    AttemptGraded::dispatch($attempt);

    // That is the point of having a passing percentage at all.
    expect(LessonProgress::query()->where('lesson_id', $this->quiz->getKey())->first()?->completed_at)
        ->toBeNull();
});

it('ignores a passed course-level test when completing lessons', function (): void {
    $test = Assessment::factory()->create([
        'assessable_type' => Course::class,
        'assessable_id' => $this->course->getKey(),
        'type' => AssessmentType::Test,
    ]);

    $attempt = AssessmentAttempt::factory()->graded()->create([
        'assessment_id' => $test->getKey(),
        'user_id' => $this->student->getKey(),
        'enrollment_id' => $this->enrollment->getKey(),
    ]);

    // A final test gates the COURSE; it does not complete a lesson (ADR-002).
    AttemptGraded::dispatch($attempt);

    expect(LessonProgress::query()->count())->toBe(0);
});

/*
| ═══════════════ NFR-PERF-04 — THE DASHBOARD STAYS BOUNDED ═══════════════
*/
it('does not issue a query per enrolled course', function (): void {
    // Twenty courses. Reading cached aggregates is the whole reason those
    // columns exist; recounting lesson rows per course would climb with this
    // number and a student with a full library would pay for it.
    foreach (range(1, 20) as $i) {
        $course = Course::factory()->published()->create();
        $module = Module::factory()->forCourse($course)->published()->create();
        Lesson::factory()->forModule($module)->published()->count(3)->create(['type' => LessonType::Text]);

        app(GrantEnrollment::class)->handle($this->student, $course, EnrollmentSource::AdminGrant, $this->admin);
    }

    $queries = 0;
    DB::listen(static function () use (&$queries): void {
        $queries++;
    });

    $this->actingAs($this->student)->get(route('student.home'))->assertOk();

    expect($queries)->toBeLessThan(30);
});
