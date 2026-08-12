<?php

declare(strict_types=1);

use App\Actions\Catalog\ArchiveCourse;
use App\Actions\Catalog\CreateCourse;
use App\Actions\Catalog\DeleteCourse;
use App\Actions\Catalog\DeleteLesson;
use App\Actions\Catalog\DeleteModule;
use App\Actions\Catalog\PublishCourse;
use App\Actions\Catalog\UnpublishCourse;
use App\Actions\Catalog\UpdateCourse;
use App\Enums\CourseStatus;
use App\Enums\LessonType;
use App\Exceptions\CoursePublishException;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Phase 5 · Course lifecycle (FR-CRS-01 … FR-CRS-06, AC-17)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
});

/**
 * A course that satisfies every publish rule.
 */
function publishableCourse(): Course
{
    $course = Course::factory()->create([
        'description' => 'A real description.',
        'thumbnail_path' => 'thumbnails/x.jpg',
    ]);

    $module = Module::factory()->forCourse($course)->published()->create();
    Lesson::factory()->forModule($module)->published()->create([
        'type' => LessonType::Text,
        'body' => '<p>Lesson content.</p>',
    ]);

    return $course->refresh();
}

it('creates a course as a draft', function (): void {
    $course = app(CreateCourse::class)->handle([
        'title' => 'Advanced Laravel',
        'price_amount' => 499900,
        'description' => 'Learn Laravel properly.',
    ], $this->admin);

    expect($course->status)->toBe(CourseStatus::Draft)
        ->and($course->published_at)->toBeNull()
        ->and($course->price_amount)->toBe(499900)
        ->and($course->created_by)->toBe($this->admin->id)
        ->and($course->slug)->toStartWith('advanced-laravel');

    expect(AuditLog::query()->where('action', 'course.created')->exists())->toBeTrue();
});

it('sanitises rich text on save, not on render', function (): void {
    $course = app(CreateCourse::class)->handle([
        'title' => 'Test',
        'price_amount' => 100000,
        'description' => '<p>Safe</p><script>alert(1)</script>',
    ], $this->admin);

    // What is IN THE DATABASE is already clean (NFR-SEC-06).
    expect($course->description)->not->toContain('script')
        ->and($course->description)->not->toContain('alert')
        ->and($course->description)->toContain('Safe');
});

it('generates a unique slug when titles collide', function (): void {
    $a = app(CreateCourse::class)->handle(['title' => 'Same Name', 'price_amount' => 100000], $this->admin);
    $b = app(CreateCourse::class)->handle(['title' => 'Same Name', 'price_amount' => 100000], $this->admin);

    expect($a->slug)->not->toBe($b->slug);
});

it('updates metadata and audits the change', function (): void {
    $course = Course::factory()->create(['title' => 'Old title']);

    $updated = app(UpdateCourse::class)->handle($course, [
        'title' => 'New title',
        'price_amount' => 250000,
    ], $this->admin);

    expect($updated->title)->toBe('New title')->and($updated->price_amount)->toBe(250000);
    expect(AuditLog::query()->where('action', 'course.updated')->exists())->toBeTrue();
});

/*
| PUBLISHING (FR-CRS-04, AC-17).
*/
it('publishes a course that satisfies every rule', function (): void {
    $course = app(PublishCourse::class)->handle(publishableCourse(), $this->admin);

    expect($course->status)->toBe(CourseStatus::Published)
        ->and($course->published_at)->not->toBeNull();

    expect(AuditLog::query()->where('action', 'course.published')->exists())->toBeTrue();
});

it('refuses to publish a course that fails validation, and says why', function (): void {
    // Bare course: no description, no thumbnail, no modules.
    $course = Course::factory()->create(['description' => null, 'thumbnail_path' => null]);

    try {
        app(PublishCourse::class)->handle($course, $this->admin);
        $this->fail('Expected CoursePublishException.');
    } catch (CoursePublishException $e) {
        // ALL reasons, not just the first — an admin fixes everything in one
        // pass rather than rediscovering the next problem after each fix.
        expect($e->reasons)->toHaveCount(3)
            ->and(implode(' ', $e->reasons))
            ->toContain('description')
            ->toContain('thumbnail')
            ->toContain('module');
    }

    expect($course->refresh()->status)->toBe(CourseStatus::Draft);
});

it('keeps the original published_at when republished', function (): void {
    $course = app(PublishCourse::class)->handle(publishableCourse(), $this->admin);
    $first = $course->published_at;

    app(UnpublishCourse::class)->handle($course, $this->admin);
    $this->travel(2)->days();
    $again = app(PublishCourse::class)->handle($course->refresh(), $this->admin);

    // The catalogue's "newest" ordering must not lie about a course's age.
    expect($again->published_at?->timestamp)->toBe($first?->timestamp);
});

/*
| UNPUBLISHING (FR-CRS-05) — the rule that protects paying students.
*/
it('unpublishes without touching anything except status', function (): void {
    $course = app(PublishCourse::class)->handle(publishableCourse(), $this->admin);
    $publishedAt = $course->published_at;

    $result = app(UnpublishCourse::class)->handle($course, $this->admin);

    expect($result->status)->toBe(CourseStatus::Draft)
        // published_at survives: it records when the course FIRST went live,
        // which stays true while it is off sale.
        ->and($result->published_at?->timestamp)->toBe($publishedAt?->timestamp);

    expect(AuditLog::query()->where('action', 'course.unpublished')->exists())->toBeTrue();
});

it('archives a course', function (): void {
    $course = app(ArchiveCourse::class)->handle(publishableCourse(), $this->admin);

    expect($course->status)->toBe(CourseStatus::Archived);
    expect(AuditLog::query()->where('action', 'course.archived')->exists())->toBeTrue();
});

/*
| DELETION (FR-CRS-06, NFR-DATA-05).
*/
it('soft deletes a course and queues its file cleanup', function (): void {
    Queue::fake();

    $course = Course::factory()->create();
    $id = $course->id;

    app(DeleteCourse::class)->handle($course, $this->admin);

    expect(Course::query()->find($id))->toBeNull()
        // Recoverable: enrollments, orders and progress reference it.
        ->and(Course::withTrashed()->find($id))->not->toBeNull();

    Queue::assertPushed(App\Jobs\Media\DeleteOrphanedMedia::class);
    expect(AuditLog::query()->where('action', 'course.deleted')->exists())->toBeTrue();
});

it('never permits permanent deletion through the policy', function (): void {
    expect($this->admin->can('forceDelete', Course::factory()->create()))->toBeFalse();
});

/*
| Cleanup is queued at EVERY level that can orphan a file, not just the
| course. A lesson soft-deleted on its own leaves its media referenced by a
| row nobody can reach — bytes paid for forever. The job is dispatched
| afterCommit so a rolled-back delete never destroys live files.
*/
it('queues media cleanup when a lesson is deleted', function (): void {
    Queue::fake();

    $module = Module::factory()->forCourse(Course::factory()->create())->create();
    $lesson = Lesson::factory()->forModule($module)->create();
    $lessonId = (int) $lesson->id;

    app(DeleteLesson::class)->handle($lesson, $this->admin);

    Queue::assertPushed(
        App\Jobs\Media\DeleteOrphanedMedia::class,
        static fn (App\Jobs\Media\DeleteOrphanedMedia $job): bool => $job->attachableType === Lesson::class
            && $job->attachableId === $lessonId,
    );
});

it('queues cleanup for every lesson when a module is deleted', function (): void {
    Queue::fake();

    $module = Module::factory()->forCourse(Course::factory()->create())->create();
    Lesson::factory()->forModule($module)->count(3)->create();

    app(DeleteModule::class)->handle($module, $this->admin);

    // One job per lesson: the module itself holds no media, its lessons do.
    Queue::assertPushed(App\Jobs\Media\DeleteOrphanedMedia::class, 3);
});

/*
| The database refuses a free course even if an action tried (ADR-014).
*/
it('cannot create a course priced at zero', function (): void {
    expect(fn () => app(CreateCourse::class)->handle([
        'title' => 'Free Course',
        'price_amount' => 0,
    ], $this->admin))->toThrow(Illuminate\Database\QueryException::class);
});
