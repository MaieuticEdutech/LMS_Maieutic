<?php

declare(strict_types=1);

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Phase 3 (Track C) — enrollments schema and model invariants
|--------------------------------------------------------------------------
|
| Guards the database-level protections the application relies on but never
| re-checks at runtime (architecture.md §6.4, §6.5, ADR-012).
|
| This table's entire reason for being tested this thoroughly: it is the
| storage behind "who has access to what." EnrollmentAccessService and
| GrantEnrollment (Govind's single-owner components) are what give it
| meaning; this file proves the storage itself cannot be corrupted.
|
*/

it('creates the enrollments table', function (): void {
    expect(Schema::hasTable('enrollments'))->toBeTrue();
});

/*
| THE IDEMPOTENCY GUARANTEE (architecture.md §6.5, Phase 3 DoD — "proven by
| a test that expects the insert to throw", the single most load-bearing
| constraint in this track). A replayed payment webhook or retried admin
| grant must not create a second enrollment for the same student and course.
*/
it('enforces one enrollment per student per course', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->create();

    Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id]);

    expect(fn () => Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id]))
        ->toThrow(QueryException::class);
});

it('allows the same student to enroll in different courses', function (): void {
    $user = User::factory()->student()->create();

    Enrollment::factory()->create(['user_id' => $user->id]);
    Enrollment::factory()->create(['user_id' => $user->id]);

    expect(Enrollment::query()->where('user_id', $user->id)->count())->toBe(2);
});

it('allows different students to enroll in the same course', function (): void {
    $course = Course::factory()->create();

    Enrollment::factory()->create(['course_id' => $course->id]);
    Enrollment::factory()->create(['course_id' => $course->id]);

    expect(Enrollment::query()->where('course_id', $course->id)->count())->toBe(2);
});

/*
| CHECK CONSTRAINTS (ADR-012).
*/
it('rejects an invalid status at the database level', function (): void {
    $enrollment = Enrollment::factory()->create();

    expect(fn () => DB::table('enrollments')->where('id', $enrollment->id)->update(['status' => 'pending_review']))
        ->toThrow(QueryException::class);
});

it('rejects an invalid source at the database level', function (): void {
    $enrollment = Enrollment::factory()->create();

    expect(fn () => DB::table('enrollments')->where('id', $enrollment->id)->update(['source' => 'gift']))
        ->toThrow(QueryException::class);
});

it('accepts every status and source combination the application can produce', function (EnrollmentStatus $status, EnrollmentSource $source): void {
    $enrollment = Enrollment::factory()->create();
    $enrollment->forceFill(['status' => $status, 'source' => $source])->save();

    expect($enrollment->refresh()->status)->toBe($status)
        ->and($enrollment->source)->toBe($source);
})->with('status and source combinations');

it('rejects a progress_percentage outside 0-100', function (int $value): void {
    $enrollment = Enrollment::factory()->create();

    expect(fn () => DB::table('enrollments')->where('id', $enrollment->id)->update(['progress_percentage' => $value]))
        ->toThrow(QueryException::class);
})->with([-1, 101]);

/*
| DELETE BEHAVIOUR — user_id/course_id are not nullable and RESTRICT;
| order_id/granted_by/revoked_by/last_lesson_id are nullable and SET NULL.
*/
it('refuses to delete a user who has an enrollment', function (): void {
    $user = User::factory()->student()->create();
    Enrollment::factory()->create(['user_id' => $user->id]);

    expect(fn () => $user->forceDelete())->toThrow(QueryException::class);
});

it('refuses to delete a course that has an enrollment', function (): void {
    $course = Course::factory()->create();
    Enrollment::factory()->create(['course_id' => $course->id]);

    expect(fn () => $course->forceDelete())->toThrow(QueryException::class);
});

it('keeps an enrollment when its order is deleted', function (): void {
    $order = Order::factory()->paid()->create();
    $enrollment = Enrollment::factory()->create(['order_id' => $order->id]);

    $order->delete();

    $survivor = Enrollment::query()->find($enrollment->id);

    expect($survivor)->not->toBeNull()
        ->and($survivor?->order_id)->toBeNull();
});

it('keeps an enrollment when its last accessed lesson is deleted', function (): void {
    $lesson = Lesson::factory()->create();
    $enrollment = Enrollment::factory()->create(['last_lesson_id' => $lesson->id]);

    $lesson->forceDelete();

    $survivor = Enrollment::query()->find($enrollment->id);

    expect($survivor)->not->toBeNull()
        ->and($survivor?->last_lesson_id)->toBeNull();
});

/*
| OWNERSHIP AND ACCESS-STATE FIELDS — never mass-assignable (NFR-SEC-07).
*/
it('refuses to mass-assign identity, access-state or audit fields', function (array $payload): void {
    expect(fn () => Enrollment::factory()->make()->fill($payload))
        ->toThrow(Exception::class);
})->with([
    'user_id' => [['user_id' => 1]],
    'course_id' => [['course_id' => 1]],
    'status' => [['status' => EnrollmentStatus::Active]],
    'source' => [['source' => EnrollmentSource::Purchase]],
    'granted_by' => [['granted_by' => 1]],
    'revoked_by' => [['revoked_by' => 1]],
    'progress_percentage' => [['progress_percentage' => 50]],
    'completed_lessons_count' => [['completed_lessons_count' => 5]],
]);

/*
| SCOPE — a status filter, NOT the access gate (see class docblock).
*/
it('scopes to active enrollments only', function (): void {
    Enrollment::factory()->count(2)->create();
    Enrollment::factory()->suspended()->create();
    Enrollment::factory()->completed()->create();

    expect(Enrollment::query()->active()->count())->toBe(2);
});

dataset('status and source combinations', function (): array {
    $cases = [];
    foreach (EnrollmentStatus::cases() as $status) {
        foreach (EnrollmentSource::cases() as $source) {
            $cases["{$status->value}/{$source->value}"] = [$status, $source];
        }
    }

    return $cases;
});
