<?php

declare(strict_types=1);

use App\Enums\CompletionSource;
use App\Enums\ProgressStatus;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Phase 3 (Track C) — lesson_progress schema and model invariants
|--------------------------------------------------------------------------
|
| Guards the database-level protections the application relies on but never
| re-checks at runtime (architecture.md §6.4, §6.5, §17, ADR-012).
|
*/

it('creates the lesson_progress table', function (): void {
    expect(Schema::hasTable('lesson_progress'))->toBeTrue();
});

/*
| CONCURRENCY GUARANTEE (architecture.md §6.5, §17.2, FR-PROG-14, AC-32,
| Phase 3 DoD — "makes progress writes a safe upsert under concurrency").
*/
it('enforces one progress row per lesson per enrollment', function (): void {
    $enrollment = Enrollment::factory()->create();
    $lesson = Lesson::factory()->create();

    LessonProgress::factory()->create(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]);

    expect(fn () => LessonProgress::factory()->create(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]))
        ->toThrow(QueryException::class);
});

it('allows the same enrollment to have progress on different lessons', function (): void {
    $enrollment = Enrollment::factory()->create();

    LessonProgress::factory()->create(['enrollment_id' => $enrollment->id]);
    LessonProgress::factory()->create(['enrollment_id' => $enrollment->id]);

    expect(LessonProgress::query()->where('enrollment_id', $enrollment->id)->count())->toBe(2);
});

/*
| CHECK CONSTRAINTS (ADR-012).
*/
it('rejects an invalid status at the database level', function (): void {
    $progress = LessonProgress::factory()->create();

    expect(fn () => DB::table('lesson_progress')->where('id', $progress->id)->update(['status' => 'skipped']))
        ->toThrow(QueryException::class);
});

it('rejects an invalid completion_source at the database level', function (): void {
    $progress = LessonProgress::factory()->create();

    expect(fn () => DB::table('lesson_progress')->where('id', $progress->id)->update(['completion_source' => 'auto']))
        ->toThrow(QueryException::class);
});

it('allows a null completion_source', function (): void {
    $progress = LessonProgress::factory()->create(['completion_source' => null]);

    expect($progress->refresh()->completion_source)->toBeNull();
});

it('accepts every status the application can produce', function (ProgressStatus $status): void {
    $progress = LessonProgress::factory()->create();
    $progress->forceFill(['status' => $status])->save();

    expect($progress->refresh()->status)->toBe($status);
})->with('progress statuses');

it('accepts every completion source the application can produce', function (CompletionSource $source): void {
    $progress = LessonProgress::factory()->create();
    $progress->forceFill(['completion_source' => $source])->save();

    expect($progress->refresh()->completion_source)->toBe($source);
})->with('completion sources');

/*
| CASCADE / RESTRICT — enrollment_id and lesson_id CASCADE (architecture.md
| §6.4 explicitly specifies CASCADE for enrollment_id; the same reasoning
| extends to lesson_id — see migration docblock). user_id RESTRICTs, though
| in practice enrollments.user_id's own RESTRICT fires first for any row
| reachable through a live enrollment.
*/
it('deletes progress rows when the enrollment is deleted', function (): void {
    $enrollment = Enrollment::factory()->create();
    $progress = LessonProgress::factory()->create(['enrollment_id' => $enrollment->id]);

    // Enrollment has no SoftDeletes trait — delete() is already a hard delete.
    $enrollment->delete();

    expect(LessonProgress::query()->find($progress->id))->toBeNull();
});

it('deletes progress rows when the lesson is deleted', function (): void {
    $lesson = Lesson::factory()->create();
    $progress = LessonProgress::factory()->create(['lesson_id' => $lesson->id]);

    $lesson->forceDelete();

    expect(LessonProgress::query()->find($progress->id))->toBeNull();
});

it('refuses to delete a user directly referenced by a progress row', function (): void {
    $user = User::factory()->student()->create();
    LessonProgress::factory()->create(['user_id' => $user->id]);

    expect(fn () => $user->forceDelete())->toThrow(QueryException::class);
});

/*
| OWNERSHIP AND STATE FIELDS — never mass-assignable (NFR-SEC-07).
*/
it('refuses to mass-assign identity or state fields', function (array $payload): void {
    expect(fn () => LessonProgress::factory()->make()->fill($payload))
        ->toThrow(Exception::class);
})->with([
    'enrollment_id' => [['enrollment_id' => 1]],
    'lesson_id' => [['lesson_id' => 1]],
    'user_id' => [['user_id' => 1]],
    'status' => [['status' => ProgressStatus::Completed]],
    'completion_source' => [['completion_source' => CompletionSource::Manual]],
]);

/*
| RELATIONSHIP.
*/
it('lists an enrollment\'s lesson progress through the owning relation', function (): void {
    $enrollment = Enrollment::factory()->create();
    LessonProgress::factory()->count(3)->create(['enrollment_id' => $enrollment->id]);

    expect($enrollment->lessonProgress()->count())->toBe(3);
});

/*
| CASTS.
*/
it('casts status, completion_source and video fields correctly', function (): void {
    $progress = LessonProgress::factory()->completed(CompletionSource::Assessment)->create([
        'video_watched_seconds' => 300,
    ]);

    expect($progress->status)->toBe(ProgressStatus::Completed)
        ->and($progress->completion_source)->toBe(CompletionSource::Assessment)
        ->and($progress->video_watched_seconds)->toBe(300)
        ->and($progress->completed_at?->isToday())->toBeTrue();
});

dataset('progress statuses', fn (): array => ProgressStatus::cases());
dataset('completion sources', fn (): array => CompletionSource::cases());
