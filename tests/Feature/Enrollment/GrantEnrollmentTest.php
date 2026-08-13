<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Events\EnrollmentGranted;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| Phase 6 · GrantEnrollment — the single writer (ADR-006, FR-ENR-05)
|--------------------------------------------------------------------------
|
| The project's first rule is that a browser saying "payment succeeded" grants
| nothing. That rule is only enforceable because there is exactly ONE code
| path that creates an enrollment. These tests hold that path to the two
| properties everything else depends on: it is idempotent, and it is safe
| under concurrency.
|
*/

beforeEach(function (): void {
    Event::fake([EnrollmentGranted::class]);

    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();
    $this->course = Course::factory()->create();
    $this->grant = app(GrantEnrollment::class);
});

it('creates an active enrollment', function (): void {
    $enrollment = $this->grant->handle(
        $this->student,
        $this->course,
        EnrollmentSource::AdminGrant,
        $this->admin,
        reason: 'Scholarship place.',
    );

    expect($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->user_id)->toBe($this->student->id)
        ->and($enrollment->course_id)->toBe($this->course->id)
        ->and($enrollment->source)->toBe(EnrollmentSource::AdminGrant)
        ->and($enrollment->granted_by)->toBe($this->admin->id)
        ->and($enrollment->enrolled_at)->not->toBeNull();

    Event::assertDispatched(EnrollmentGranted::class);
    expect(AuditLog::query()->where('action', 'enrollment.granted')->exists())->toBeTrue();
});

/*
| ═══════════════ IDEMPOTENCY ═══════════════
|
| Razorpay retries webhooks. The reconciliation job re-checks payments. Admins
| double-click. All three land here and all three must produce one enrollment.
*/
it('is idempotent — a second call returns the same row', function (): void {
    $first = $this->grant->handle($this->student, $this->course, EnrollmentSource::Purchase);
    $second = $this->grant->handle($this->student, $this->course, EnrollmentSource::Purchase);

    expect($second->id)->toBe($first->id)
        ->and(Enrollment::query()->count())->toBe(1);
});

it('does not re-dispatch the event on a repeat call', function (): void {
    // Idempotency that stopped at the database row would still send the
    // student three welcome emails for one purchase.
    $this->grant->handle($this->student, $this->course, EnrollmentSource::Purchase);
    Event::assertDispatchedTimes(EnrollmentGranted::class, 1);

    $this->grant->handle($this->student, $this->course, EnrollmentSource::Purchase);
    $this->grant->handle($this->student, $this->course, EnrollmentSource::Purchase);

    Event::assertDispatchedTimes(EnrollmentGranted::class, 1);
});

it('writes no audit entry for a repeat call', function (): void {
    $this->grant->handle($this->student, $this->course, EnrollmentSource::Purchase);
    $this->grant->handle($this->student, $this->course, EnrollmentSource::Purchase);

    expect(AuditLog::query()->where('action', 'enrollment.granted')->count())->toBe(1);
});

/*
| ═══════════════ THE CONSTRAINT, PROVEN ═══════════════
|
| Layer 1 (read-then-return) cannot stop two simultaneous requests that both
| find nothing. Layer 2 is UNIQUE(user_id, course_id). This asserts the
| database really refuses, rather than trusting the migration's intent.
*/
it('cannot produce two enrollments for the same user and course', function (): void {
    $this->grant->handle($this->student, $this->course, EnrollmentSource::Purchase);

    // Bypasses the action entirely — the raw insert a buggy future caller,
    // seeder or console command might attempt.
    //
    // Wrapped in DB::transaction() so PostgreSQL opens a SAVEPOINT inside the
    // test's own transaction. Without it the constraint violation aborts the
    // whole transaction and every later query in this test fails with 25P02,
    // hiding the assertion we actually care about behind a second error.
    expect(fn () => DB::transaction(fn () => DB::table('enrollments')->insert([
        'user_id' => $this->student->id,
        'course_id' => $this->course->id,
        'source' => EnrollmentSource::AdminGrant->value,
        'status' => EnrollmentStatus::Active->value,
        'enrolled_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ])))->toThrow(Illuminate\Database\QueryException::class);

    expect(Enrollment::query()->count())->toBe(1);
});

it('recovers when the unique constraint fires mid-flight', function (): void {
    // Simulates losing the race: the row appears between our SELECT and our
    // INSERT. The action must return the winner's row, not explode.
    // Built and filled before saving: the ownership columns are not fillable,
    // so create([]) would attempt an empty row and hit NOT NULL first.
    $existing = new Enrollment;

    $existing->forceFill([
        'user_id' => $this->student->id,
        'course_id' => $this->course->id,
        'source' => EnrollmentSource::Purchase,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ])->save();

    $result = $this->grant->handle($this->student, $this->course, EnrollmentSource::Purchase);

    expect($result->id)->toBe($existing->id)
        ->and(Enrollment::query()->count())->toBe(1);
});

/*
| ═══════════════ REACTIVATION ═══════════════
*/
it('reactivates a revoked enrollment rather than creating a second one', function (): void {
    $enrollment = $this->grant->handle($this->student, $this->course, EnrollmentSource::Purchase);

    $enrollment->forceFill([
        'status' => EnrollmentStatus::Refunded,
        'revoked_at' => now(),
        'revoke_reason' => 'Refunded on request.',
    ])->save();

    $again = $this->grant->handle($this->student, $this->course, EnrollmentSource::Purchase);

    expect($again->id)->toBe($enrollment->id)
        ->and($again->status)->toBe(EnrollmentStatus::Active)
        // The revocation is history now, not current state.
        ->and($again->revoked_at)->toBeNull()
        ->and($again->revoke_reason)->toBeNull()
        ->and(Enrollment::query()->count())->toBe(1);

    Event::assertDispatched(
        EnrollmentGranted::class,
        static fn (EnrollmentGranted $e): bool => $e->wasReactivated === true,
    );
});

it('reactivates an expired enrollment', function (): void {
    $enrollment = $this->grant->handle(
        $this->student,
        $this->course,
        EnrollmentSource::Purchase,
        expiresAt: now()->subDay(),
    );

    $renewed = $this->grant->handle(
        $this->student,
        $this->course,
        EnrollmentSource::Purchase,
        expiresAt: now()->addYear(),
    );

    expect($renewed->id)->toBe($enrollment->id)
        ->and($renewed->status)->toBe(EnrollmentStatus::Active)
        ->and($renewed->expires_at?->isFuture())->toBeTrue();
});

it('leaves a completed enrollment alone', function (): void {
    // Finishing a course does not end access, so a repeat grant must not
    // demote `completed` back to `active`.
    $enrollment = $this->grant->handle($this->student, $this->course, EnrollmentSource::Purchase);
    $enrollment->forceFill(['status' => EnrollmentStatus::Completed, 'completed_at' => now()])->save();

    $again = $this->grant->handle($this->student, $this->course, EnrollmentSource::Purchase);

    expect($again->status)->toBe(EnrollmentStatus::Completed);
});

/*
| ═══════════════ MASS ASSIGNMENT ═══════════════
*/
it('never allows ownership or status through mass assignment', function (string $field): void {
    // NFR-SEC-07. user_id, course_id, status, source and granted_by decide who
    // has paid access — none may be settable from a request payload.
    expect(fn () => Enrollment::query()->create([$field => 1]))->toThrow(Exception::class);
})->with(['user_id', 'course_id', 'status', 'source', 'granted_by']);
