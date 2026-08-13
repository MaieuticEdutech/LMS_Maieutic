<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Actions\Enrollment\ReinstateEnrollment;
use App\Actions\Enrollment\RevokeEnrollment;
use App\Actions\Enrollment\SuspendEnrollment;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Events\EnrollmentRevoked;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\User;
use App\Services\Enrollment\EnrollmentAccessService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| Phase 6 · Enrollment lifecycle and scheduled expiry (FR-ENR-08 … FR-ENR-10)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Event::fake([EnrollmentRevoked::class]);

    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();
    $this->course = Course::factory()->create();

    $this->enrollment = app(GrantEnrollment::class)->handle(
        $this->student,
        $this->course,
        EnrollmentSource::Purchase,
    );

    $this->access = app(EnrollmentAccessService::class);
});

/*
| REVOCATION
*/
it('revokes access and records who, when and why', function (): void {
    app(RevokeEnrollment::class)->handle($this->enrollment, $this->admin, 'Chargeback received.');

    $fresh = $this->enrollment->refresh();

    expect($fresh->status)->toBe(EnrollmentStatus::Expired)
        ->and($fresh->revoked_by)->toBe($this->admin->id)
        ->and($fresh->revoked_at)->not->toBeNull()
        ->and($fresh->revoke_reason)->toBe('Chargeback received.')
        ->and($this->access->grantsAccess($this->student, $this->course))->toBeFalse();

    Event::assertDispatched(EnrollmentRevoked::class);
    expect(AuditLog::query()->where('action', 'enrollment.revoked')->exists())->toBeTrue();
});

it('refuses to revoke without a reason', function (string $reason): void {
    // Enforced in the action, not in a form request: a validation rule in one
    // controller leaves every other caller free to skip it. Revocation is the
    // action most likely to be questioned months later.
    expect(fn () => app(RevokeEnrollment::class)->handle($this->enrollment, $this->admin, $reason))
        ->toThrow(InvalidArgumentException::class);

    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Active);
})->with(['empty' => '', 'whitespace' => '   ']);

it('marks a refund as refunded rather than expired', function (): void {
    app(RevokeEnrollment::class)->handle($this->enrollment, $this->admin, 'Refunded.', refunded: true);

    // The commercial history has to stay legible — "expired" and "we gave the
    // money back" are different facts.
    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Refunded);
});

/*
| SUSPENSION — reversible, unlike revocation
*/
it('suspends and reinstates without losing progress', function (): void {
    $this->enrollment->forceFill(['progress_percentage' => 42])->save();

    app(SuspendEnrollment::class)->handle($this->enrollment, $this->admin, 'Payment dispute.');

    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Suspended)
        ->and($this->access->grantsAccess($this->student, $this->course))->toBeFalse();

    app(ReinstateEnrollment::class)->handle($this->enrollment, $this->admin, 'Dispute resolved.');

    $this->access->flush();

    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Active)
        // A temporary hold must not cause permanent damage.
        ->and($this->enrollment->progress_percentage)->toBe(42)
        ->and($this->access->grantsAccess($this->student, $this->course))->toBeTrue();
});

it('restores completed rather than active when reinstating a finished course', function (): void {
    $this->enrollment->forceFill([
        'status' => EnrollmentStatus::Completed,
        'completed_at' => now(),
    ])->save();

    app(SuspendEnrollment::class)->handle($this->enrollment, $this->admin, 'Under review.');
    app(ReinstateEnrollment::class)->handle($this->enrollment, $this->admin);

    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Completed);
});

it('refuses to suspend an already terminal enrollment', function (): void {
    // Suspending a revoked row would overwrite a terminal state with a
    // reversible one and lose the revocation record.
    app(RevokeEnrollment::class)->handle($this->enrollment, $this->admin, 'Revoked.');

    expect(fn () => app(SuspendEnrollment::class)->handle($this->enrollment, $this->admin, 'Late.'))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses to reinstate anything that was not suspended', function (): void {
    // Reviving a revoked enrollment is a NEW GRANT, and new grants go through
    // GrantEnrollment — the single writer. Allowing it here would put a second
    // door beside the one ADR-006 exists to keep single.
    app(RevokeEnrollment::class)->handle($this->enrollment, $this->admin, 'Refunded.', refunded: true);

    expect(fn () => app(ReinstateEnrollment::class)->handle($this->enrollment, $this->admin))
        ->toThrow(InvalidArgumentException::class);
});

/*
| SCHEDULED EXPIRY (FR-ENR-10)
*/
it('expires enrollments whose window has closed', function (): void {
    $this->enrollment->forceFill(['expires_at' => now()->subHour()])->save();

    expect(Artisan::call('lms:enrollments:expire'))->toBe(0);

    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Expired);
    Event::assertDispatched(
        EnrollmentRevoked::class,
        static fn (EnrollmentRevoked $e): bool => $e->wasAutomatic === true,
    );
});

it('leaves enrollments that have not expired alone', function (): void {
    $this->enrollment->forceFill(['expires_at' => now()->addYear()])->save();

    Artisan::call('lms:enrollments:expire');

    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Active);
});

it('leaves enrollments with no expiry alone', function (): void {
    Artisan::call('lms:enrollments:expire');

    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Active);
});

it('is safe to run twice', function (): void {
    $this->enrollment->forceFill(['expires_at' => now()->subHour()])->save();

    Artisan::call('lms:enrollments:expire');
    Artisan::call('lms:enrollments:expire');

    // The second run finds nothing, so no second audit entry and no second
    // notification to a student who already had one.
    expect(AuditLog::query()->where('action', 'enrollment.expired')->count())->toBe(1);
    Event::assertDispatchedTimes(EnrollmentRevoked::class, 1);
});

it('reports without writing in dry-run mode', function (): void {
    $this->enrollment->forceFill(['expires_at' => now()->subHour()])->save();

    Artisan::call('lms:enrollments:expire', ['--dry-run' => true]);

    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Active);
});

/*
| ACCESS IS NOT WAITING ON THE SCHEDULER.
|
| The single most important property of the expiry design: access is correct
| whether or not the command has ever run. If it were not, a stopped scheduler
| would silently extend everyone's access, and the failure would look exactly
| like normal operation.
*/
it('denies an expired enrollment before the scheduler has touched it', function (): void {
    $this->enrollment->forceFill(['expires_at' => now()->subSecond()])->save();
    $this->access->flush();

    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Active)
        ->and($this->access->grantsAccess($this->student, $this->course))->toBeFalse();
});
