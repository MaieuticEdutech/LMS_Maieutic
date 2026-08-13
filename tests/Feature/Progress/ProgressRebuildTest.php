<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Actions\Student\RecordLessonProgress;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonType;
use App\Events\CourseCompleted;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| Phase 9 · lms:progress:rebuild (FR-PROG-11)
|--------------------------------------------------------------------------
|
| ═════════════════════════════════════════════════════════════════════════
| THIS FILE IS THE PROOF THAT THE CACHE IS A CACHE.
|
| ADR-008 claims `enrollments.progress_percentage` is derived from
| `lesson_progress` and never authoritative. That claim is only worth
| something if it can be checked, and this is the check: corrupt the stored
| figures, rebuild, and every one must come back identical.
|
| If it could not, a drift would be permanent — there would be nothing to
| recompute FROM. That is the whole difference between a cache and data.
| ═════════════════════════════════════════════════════════════════════════
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();

    $this->course = Course::factory()->published()->create();
    $module = Module::factory()->forCourse($this->course)->published()->create();

    // Kept as a plain array: Collection::offsetGet is nullable, and an
    // indexed read below would be `Lesson|null` for no reason.
    $this->lessons = Lesson::factory()->forModule($module)->published()->count(4)
        ->sequence(fn ($sequence) => ['position' => $sequence->index])
        ->create(['type' => LessonType::Text])
        ->all();

    $this->enrollment = app(GrantEnrollment::class)
        ->handle($this->student, $this->course, EnrollmentSource::AdminGrant, $this->admin);

    app(RecordLessonProgress::class)->handle($this->enrollment, $this->lessons[0], completed: true);
});

it('reports no drift on a healthy system', function (): void {
    // Artisan::call rather than `$this->artisan()`: it returns an int the rest
    // of this codebase already asserts against (EnrollmentLifecycleTest), and
    // the PendingCommand fluent API is not statically resolvable at level 8.
    expect(Artisan::call('lms:progress:rebuild'))->toBe(0)
        ->and(Artisan::output())->toContain('Every figure already matched.');
});

it('restores figures that have been corrupted', function (): void {
    // The state a bug, a bad deploy or a manual UPDATE would leave behind.
    $this->enrollment->forceFill([
        'progress_percentage' => 99,
        'completed_lessons_count' => 17,
    ])->save();

    expect(Artisan::call('lms:progress:rebuild'))->toBe(0);

    $enrollment = $this->enrollment->refresh();

    expect($enrollment->progress_percentage)->toBe(25)
        ->and($enrollment->completed_lessons_count)->toBe(1);
});

it('reports drift without causing it when asked to dry run', function (): void {
    $this->enrollment->forceFill(['progress_percentage' => 99])->save();

    expect(Artisan::call('lms:progress:rebuild', ['--dry-run' => true]))->toBe(0)
        ->and(Artisan::output())->toContain('would change');

    // Reporting must not also repair. A dry run that wrote would be a dry run
    // in name only.
    expect($this->enrollment->refresh()->progress_percentage)->toBe(99);
});

it('does not email a certificate for a course it was only inspecting', function (): void {
    Event::fake([CourseCompleted::class]);

    foreach ($this->lessons as $lesson) {
        app(RecordLessonProgress::class)->handle($this->enrollment, $lesson, completed: true);
    }

    Event::assertDispatchedTimes(CourseCompleted::class, 1);

    // The obvious dry-run implementation — recalculate, compare, restore —
    // would write twice and fire this again, meaning "just checking" told a
    // student they had finished.
    expect(Artisan::call('lms:progress:rebuild', ['--dry-run' => true]))->toBe(0);

    Event::assertDispatchedTimes(CourseCompleted::class, 1);
});

it('rebuilds a completion that was lost from the enrollment', function (): void {
    foreach ($this->lessons as $lesson) {
        app(RecordLessonProgress::class)->handle($this->enrollment, $lesson, completed: true);
    }

    $this->enrollment->forceFill([
        'status' => EnrollmentStatus::Active,
        'completed_at' => null,
        'progress_percentage' => 0,
        'completed_lessons_count' => 0,
    ])->save();

    expect(Artisan::call('lms:progress:rebuild'))->toBe(0);

    $enrollment = $this->enrollment->refresh();

    expect($enrollment->progress_percentage)->toBe(100)
        ->and($enrollment->status)->toBe(EnrollmentStatus::Completed)
        ->and($enrollment->completed_at)->not->toBeNull();
});

it('limits itself to one course when told to', function (): void {
    $other = Course::factory()->published()->create();
    $otherEnrollment = app(GrantEnrollment::class)
        ->handle($this->student, $other, EnrollmentSource::AdminGrant, $this->admin);

    $otherEnrollment->forceFill(['progress_percentage' => 77])->save();
    $this->enrollment->forceFill(['progress_percentage' => 88])->save();

    expect(Artisan::call('lms:progress:rebuild', ['--course' => $this->course->getKey()]))->toBe(0);

    expect($this->enrollment->refresh()->progress_percentage)->toBe(25)
        // Untouched, because it was out of scope.
        ->and($otherEnrollment->refresh()->progress_percentage)->toBe(77);
});

it('says so plainly when there is nothing to rebuild', function (): void {
    expect(Artisan::call('lms:progress:rebuild', ['--course' => 999999]))->toBe(0)
        ->and(Artisan::output())->toContain('No enrollments to rebuild.');
});
