<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Actions\Student\RecordLessonProgress;
use App\Enums\CompletionSource;
use App\Enums\CompletionStrategy;
use App\Enums\EnrollmentSource;
use App\Enums\LessonType;
use App\Enums\ProgressStatus;
use App\Events\LessonCompleted;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| Phase 9 · Lesson progress rules (FR-PROG-01 … FR-PROG-05, AC-32)
|--------------------------------------------------------------------------
|
| The three properties in RecordLessonProgress each exist because the obvious
| implementation is wrong in a way a student would feel: a completion that
| regresses, a watch record erased by scrubbing back, or a write per video
| frame. Each has its own section below.
|
| These assert BEHAVIOUR, not columns. LessonProgressSchemaTest covers the
| table; what matters here is what a student ends up with.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();

    $this->course = Course::factory()->published()->create();
    $this->module = Module::factory()->forCourse($this->course)->published()->create();

    $this->text = Lesson::factory()->forModule($this->module)->published()->atPosition(0)
        ->create(['type' => LessonType::Text]);

    // A round duration so a percentage is a position and needs no arithmetic
    // to read in an assertion.
    $this->video = Lesson::factory()->forModule($this->module)->published()->atPosition(1)
        ->create(['type' => LessonType::Video, 'duration_seconds' => 100]);

    $this->enrollment = app(GrantEnrollment::class)
        ->handle($this->student, $this->course, EnrollmentSource::AdminGrant, $this->admin);

    $this->action = fn (): RecordLessonProgress => app(RecordLessonProgress::class);
});

/*
| ═══════════════ PROPERTY 1 — COMPLETION IS MONOTONIC (AC-32) ═══════════════
*/
it('does not let a later position undo a completion', function (): void {
    $action = ($this->action)();

    $action->handle($this->enrollment, $this->video, positionSeconds: 95);

    expect(LessonProgress::query()->firstOrFail()->completed_at)->not->toBeNull();

    // Re-watching from the start. The lesson is still finished.
    $progress = $action->handle($this->enrollment, $this->video, positionSeconds: 3);

    expect($progress->completed_at)->not->toBeNull()
        ->and($progress->status)->toBe(ProgressStatus::Completed);
});

it('clears a completion only when explicitly told to', function (): void {
    $action = ($this->action)();

    $action->handle($this->enrollment, $this->text, completed: true);
    expect(LessonProgress::query()->firstOrFail()->completed_at)->not->toBeNull();

    $progress = $action->handle($this->enrollment, $this->text, completed: false);

    expect($progress->completed_at)->toBeNull()
        ->and($progress->status)->toBe(ProgressStatus::InProgress)
        ->and($progress->completion_source)->toBeNull();
});

/*
| ═══════════════ PROPERTY 2 — MAX WATCHED, NOT LAST WATCHED ═══════════════
*/
it('keeps the furthest point ever watched when the student scrubs back', function (): void {
    $action = ($this->action)();

    $action->handle($this->enrollment, $this->video, positionSeconds: 60);

    // Past the throttle window, so the scrub back is genuinely recorded
    // rather than merely suppressed — which is what makes the assertion below
    // about max-watched mean something.
    $this->travel(20)->seconds();

    $progress = $action->handle($this->enrollment, $this->video, positionSeconds: 10);

    // The resume point follows the student...
    expect($progress->video_position_seconds)->toBe(10)
        // ...the evidence of having watched does not.
        ->and($progress->video_watched_seconds)->toBe(60);
});

it('clamps a position outside the video', function (): void {
    $action = ($this->action)();

    expect($action->handle($this->enrollment, $this->video, positionSeconds: -30)->video_position_seconds)->toBe(0);

    // A browser's idea of duration can round past ours. Storing a position
    // beyond the file would leave the student unable to resume.
    expect($action->handle($this->enrollment, $this->video, positionSeconds: 5_000)->video_position_seconds)->toBe(100);
});

/*
| ═══════════════ PROPERTY 3 — WRITES ARE THROTTLED (FR-PROG-02) ═══════════════
|
| The trap this guards: a video reports a DIFFERENT position several times a
| second, so a throttle that only suppressed identical calls would suppress
| almost nothing.
*/
it('suppresses a position report inside the throttle window', function (): void {
    $action = ($this->action)();

    $first = $action->handle($this->enrollment, $this->video, positionSeconds: 10);
    $writtenAt = $first->updated_at?->toIso8601String();

    // A tick two seconds later, at a genuinely new position.
    $second = $action->handle($this->enrollment, $this->video, positionSeconds: 12);

    expect($second->video_position_seconds)->toBe(10)
        ->and($second->updated_at?->toIso8601String())->toBe($writtenAt);
});

it('accepts a position report once the window has elapsed', function (): void {
    $action = ($this->action)();

    $action->handle($this->enrollment, $this->video, positionSeconds: 10);

    $this->travel(20)->seconds();

    expect($action->handle($this->enrollment, $this->video, positionSeconds: 12)->video_position_seconds)->toBe(12);
});

it('never throttles a completion', function (): void {
    $action = ($this->action)();

    // First call opens the window.
    $action->handle($this->enrollment, $this->video, positionSeconds: 10);

    // Immediately after, still inside it — but this one crosses the threshold
    // and must land at once. A student watching the bar must not see their
    // completion arrive fifteen seconds late.
    $progress = $action->handle($this->enrollment, $this->video, positionSeconds: 92);

    expect($progress->completed_at)->not->toBeNull();
});

it('records a first visit even though the row is brand new', function (): void {
    // The row's updated_at is `now`, so a naive throttle would swallow the
    // very first call and the resume pointer would never be set (AC-29).
    ($this->action)()->handle($this->enrollment, $this->text);

    expect($this->enrollment->refresh()->last_lesson_id)->toBe($this->text->getKey())
        ->and($this->enrollment->last_accessed_at)->not->toBeNull();
});

/*
| ═══════════════ THE VIDEO THRESHOLD (FR-PROG-04) ═══════════════
*/
it('completes a video at the configured threshold and not before', function (): void {
    $action = ($this->action)();

    // 89 of 100 seconds, against a default threshold of 90%.
    expect($action->handle($this->enrollment, $this->video, positionSeconds: 89)->completed_at)->toBeNull();

    $this->travel(20)->seconds();

    expect($action->handle($this->enrollment, $this->video, positionSeconds: 90)->completed_at)->not->toBeNull();
});

it('follows the threshold an administrator configured', function (): void {
    app(SettingsRepository::class)->set('progress.video_completion_threshold', 50, 'progress', 'integer');

    $progress = ($this->action)()->handle($this->enrollment, $this->video, positionSeconds: 50);

    expect($progress->completed_at)->not->toBeNull()
        ->and($progress->completion_source)->toBe(CompletionSource::Video);
});

it('refuses to guess when the video has no known duration', function (): void {
    $unknown = Lesson::factory()->forModule($this->module)->published()->atPosition(2)
        ->create(['type' => LessonType::Video, 'duration_seconds' => null]);

    // Completing on the first tick because 0 seconds is "all of nothing" is
    // the failure this guards.
    expect(($this->action)()->handle($this->enrollment, $unknown, positionSeconds: 5)->completed_at)->toBeNull();
});

/*
| ═══════════════ WHO MAY COMPLETE WHAT ═══════════════
|
| The permission matrix is the guard that stops each completion path
| completing a lesson the other one owns.
*/
it('accepts a manual completion only where the type allows it', function (LessonType $type, bool $allowed): void {
    $lesson = Lesson::factory()->forModule($this->module)->published()->atPosition(9)
        ->create(['type' => $type, 'duration_seconds' => 100]);

    $progress = ($this->action)()->handle($this->enrollment, $lesson, completed: true);

    expect($progress->completed_at !== null)->toBe($allowed);
})->with([
    'text completes manually' => [LessonType::Text, true],
    'document completes manually' => [LessonType::Document, true],
    'video does not' => [LessonType::Video, false],
    'quiz does not' => [LessonType::Quiz, false],
]);

it('refuses an assessment completion on a lesson that is not assessed', function (): void {
    // An attempt arriving late for a lesson whose type has since changed must
    // not complete it under the old rule.
    $progress = ($this->action)()->handle(
        $this->enrollment,
        $this->text,
        completed: true,
        source: CompletionStrategy::Assessment,
    );

    expect($progress->completed_at)->toBeNull();
});

it('records how a lesson was completed, not merely that it was', function (): void {
    $action = ($this->action)();

    $manual = $action->handle($this->enrollment, $this->text, completed: true);
    $watched = $action->handle($this->enrollment, $this->video, positionSeconds: 95);

    expect($manual->completion_source)->toBe(CompletionSource::Manual)
        ->and($watched->completion_source)->toBe(CompletionSource::Video);
});

it('maps every strategy onto a source the database will accept', function (CompletionStrategy $strategy): void {
    // The CHECK constraint on lesson_progress.completion_source is the real
    // arbiter (ADR-012). A strategy that mapped to something outside it would
    // be a 23514 at the moment a student completed a lesson.
    expect(CompletionSource::values())->toContain($strategy->toSource()->value);
})->with(CompletionStrategy::cases());

/*
| ═══════════════ EVENTS ═══════════════
*/
it('announces a completion once, on the transition only', function (): void {
    Event::fake([LessonCompleted::class]);

    $action = ($this->action)();

    $action->handle($this->enrollment, $this->video, positionSeconds: 95);
    $this->travel(20)->seconds();
    $action->handle($this->enrollment, $this->video, positionSeconds: 97);

    // A video that keeps reporting after the threshold would otherwise fire
    // this every tick, and each one recounts the course.
    Event::assertDispatchedTimes(LessonCompleted::class, 1);
});

/*
| ═══════════════ CONCURRENCY (AC-32) ═══════════════
*/
it('produces one row when two sessions report the same lesson at once', function (): void {
    // The real race is two requests interleaving between the read and the
    // insert. Simulated by inserting the row a second actor "won" with, then
    // letting the action take its create path into the UNIQUE constraint —
    // which is the branch that must not surface as a 500 to a student.
    $winner = new LessonProgress;
    $winner->forceFill([
        'enrollment_id' => $this->enrollment->getKey(),
        'lesson_id' => $this->text->getKey(),
        'user_id' => $this->student->getKey(),
        'status' => ProgressStatus::InProgress,
        'first_accessed_at' => now(),
        'video_watched_seconds' => 42,
    ])->save();

    $progress = ($this->action)()->handle($this->enrollment, $this->text, completed: true);

    expect(LessonProgress::query()->count())->toBe(1)
        ->and($progress->getKey())->toBe($winner->getKey())
        // The loser's work is applied to the winner's row rather than lost.
        ->and($progress->completed_at)->not->toBeNull()
        ->and($progress->video_watched_seconds)->toBe(42);
});
