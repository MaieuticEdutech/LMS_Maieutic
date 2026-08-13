<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Livewire\Admin\Enrollments\EnrollmentsTable;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\EnrollmentRevokedNotification;
use App\Services\Enrollment\EnrollmentAccessService;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Phase 6 UI — admin enrolments table (FR-ENR-07, FR-ENR-08, FR-ADM-17)
|--------------------------------------------------------------------------
|
| This screen takes away paid access, so the tests that matter most are the
| ones about who may use it and what it refuses to do — not what it renders.
|
| The component owns no business logic: every mutation delegates to Track A's
| single-owner actions. These assert the DELEGATION and the guards around it,
| and leave the state machine itself to Track A's own tests.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create(['name' => 'Asha Rao']);
    $this->course = Course::factory()->create(['title' => 'Advanced Widgetry']);

    $this->enrollment = app(GrantEnrollment::class)->handle(
        student: $this->student,
        course: $this->course,
        source: EnrollmentSource::AdminGrant,
        actor: $this->admin,
    );
});

/*
| ═════════════ AUTHORISATION — the part a UI mistake must not undo ═════════════
*/
it('refuses a student', function (): void {
    // The route middleware also blocks this, but the component must refuse on
    // its own: middleware answers "may this kind of user be here?", the policy
    // answers "may THIS user touch THIS record?" (FR-RBAC-02/03).
    $this->actingAs(User::factory()->create());

    Livewire::test(EnrollmentsTable::class)
        ->call('confirmRevoke', $this->enrollment->id)
        ->set('reason', 'Trying it on')
        ->set('confirmation', 'REVOKE')
        ->call('revoke')
        ->assertForbidden();

    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Active);
});

it('refuses an instructor', function (): void {
    $this->actingAs(User::factory()->instructor()->create());

    Livewire::test(EnrollmentsTable::class)
        ->call('confirmRevoke', $this->enrollment->id)
        ->set('reason', 'Not mine to do')
        ->set('confirmation', 'REVOKE')
        ->call('revoke')
        ->assertForbidden();

    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Active);
});

it('re-authorises the record rather than trusting the id from the browser', function (): void {
    /*
     * `actingOnId` arrives from the client and can be set to anything. The
     * component re-fetches and re-authorises on every call — hiding a control
     * is never security (Rule 20).
     */
    $this->actingAs(User::factory()->create());

    Livewire::test(EnrollmentsTable::class)
        ->set('actingOnId', $this->enrollment->id)
        ->set('reason', 'Straight to the action')
        ->set('confirmation', 'REVOKE')
        ->call('revoke')
        ->assertForbidden();
});

/*
| ═════════════ FR-ADM-17 — TYPED CONFIRMATION ═════════════
*/
it('refuses to revoke without the typed confirmation', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(EnrollmentsTable::class)
        ->call('confirmRevoke', $this->enrollment->id)
        ->set('reason', 'Refund processed')
        ->set('confirmation', '')
        ->call('revoke')
        ->assertHasErrors('confirmation');

    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Active);
});

it('refuses a confirmation that is merely close', function (): void {
    // A red button plus a click is not confirmation — the word is the control.
    $this->actingAs($this->admin);

    Livewire::test(EnrollmentsTable::class)
        ->call('confirmRevoke', $this->enrollment->id)
        ->set('reason', 'Refund processed')
        ->set('confirmation', 'revoke')
        ->call('revoke')
        ->assertHasErrors('confirmation');

    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Active);
});

it('refuses to revoke without a reason', function (): void {
    // RevokeEnrollment throws without one; the form catches it first so the
    // administrator sees a field error rather than an exception.
    $this->actingAs($this->admin);

    Livewire::test(EnrollmentsTable::class)
        ->call('confirmRevoke', $this->enrollment->id)
        ->set('reason', '')
        ->set('confirmation', 'REVOKE')
        ->call('revoke')
        ->assertHasErrors('reason');

    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Active);
});

/*
| ═════════════ THE HAPPY PATHS, END TO END ═════════════
*/
it('revokes access, audits it, and emails the student', function (): void {
    Notification::fake();
    $this->actingAs($this->admin);

    Livewire::test(EnrollmentsTable::class)
        ->call('confirmRevoke', $this->enrollment->id)
        ->set('reason', 'Course withdrawn from sale')
        ->set('confirmation', 'REVOKE')
        ->call('revoke')
        ->assertHasNoErrors();

    // `expired` is the default: an ordinary revocation is not a refund.
    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Expired)
        // The access gate, not a status read (rule S-8).
        ->and(app(EnrollmentAccessService::class)->grantsAccess($this->student, $this->course))->toBeFalse()
        ->and(AuditLog::query()->where('action', 'enrollment.revoked')->exists())->toBeTrue();

    Notification::assertSentTo($this->student, EnrollmentRevokedNotification::class);
});

it('records a refund distinctly from an ordinary revocation', function (): void {
    /*
     * RevokeEnrollment stores `refunded` rather than `expired` when money went
     * back, "so the commercial history stays legible". Only the administrator
     * knows which happened, so the UI has to ask — defaulting silently would
     * make Phase 13's revenue reporting quietly wrong.
     */
    $this->actingAs($this->admin);

    Livewire::test(EnrollmentsTable::class)
        ->call('confirmRevoke', $this->enrollment->id)
        ->set('reason', 'Refund processed')
        ->set('refunded', true)
        ->set('confirmation', 'REVOKE')
        ->call('revoke')
        ->assertHasNoErrors();

    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Refunded);
});

it('suspends and reinstates, restoring access', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(EnrollmentsTable::class)
        ->call('confirmSuspend', $this->enrollment->id)
        ->set('reason', 'Payment dispute under review')
        ->call('suspend')
        ->assertHasNoErrors();

    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Suspended)
        ->and(app(EnrollmentAccessService::class)->grantsAccess($this->student, $this->course))->toBeFalse();

    Livewire::test(EnrollmentsTable::class)
        ->call('reinstate', $this->enrollment->id)
        ->assertHasNoErrors();

    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Active)
        ->and(app(EnrollmentAccessService::class)->grantsAccess($this->student, $this->course))->toBeTrue();
});

it('reports an illegal transition as a form error, not a crash', function (): void {
    /*
     * Reachable from a stale page whose buttons no longer match the current
     * state — two administrators working at once, or one leaving a tab open.
     * The action refuses; this screen must explain rather than 500.
     */
    $this->actingAs($this->admin);

    Livewire::test(EnrollmentsTable::class)
        ->call('reinstate', $this->enrollment->id)
        ->assertHasErrors('reason');

    expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Active);
});

/*
| ═════════════ LISTING, FILTERING, STATES ═════════════
*/
it('lists enrolments with the student and course', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(EnrollmentsTable::class)
        ->assertSee('Asha Rao')
        ->assertSee('Advanced Widgetry');
});

it('searches across the student and the course, not the enrolment row', function (): void {
    $this->actingAs($this->admin);

    $other = User::factory()->create(['name' => 'Bhavna Singh']);
    $otherCourse = Course::factory()->create(['title' => 'Beginner Basketry']);
    app(GrantEnrollment::class)->handle(
        student: $other,
        course: $otherCourse,
        source: EnrollmentSource::AdminGrant,
        actor: $this->admin,
    );

    /*
     * Asserted on the ROWS returned, not on the rendered HTML: every course
     * title also appears in the course filter's options, so assertDontSee on a
     * title would fail even when filtering works correctly.
     */
    $names = static fn ($rows): array => $rows->pluck('user.name')->all();

    // By student name
    Livewire::test(EnrollmentsTable::class)
        ->set('search', 'Asha')
        ->assertViewHas('enrollments', fn ($rows): bool => $names($rows) === ['Asha Rao']);

    // By course title
    Livewire::test(EnrollmentsTable::class)
        ->set('search', 'Basketry')
        ->assertViewHas('enrollments', fn ($rows): bool => $names($rows) === ['Bhavna Singh']);

    // By email — the field support actually has to hand
    Livewire::test(EnrollmentsTable::class)
        ->set('search', $other->email)
        ->assertViewHas('enrollments', fn ($rows): bool => $names($rows) === ['Bhavna Singh']);
});

it('filters by status', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(EnrollmentsTable::class)
        ->set('statusFilter', EnrollmentStatus::Refunded->value)
        ->assertDontSee('Asha Rao')
        ->set('statusFilter', EnrollmentStatus::Active->value)
        ->assertSee('Asha Rao');
});

it('explains an empty table differently when filters are the reason', function (): void {
    // UI-GUIDE.md §11: never a bare "No results". A filtered empty state and a
    // genuinely empty one need different next actions.
    $this->actingAs($this->admin);

    Livewire::test(EnrollmentsTable::class)
        ->set('search', 'nobody-by-this-name')
        ->assertSee('No enrolments match those filters')
        ->assertSee('Clear filters');
});

it('shows a real empty state when there are no enrolments at all', function (): void {
    $this->actingAs($this->admin);
    Enrollment::query()->delete();

    Livewire::test(EnrollmentsTable::class)
        ->assertSee('No enrolments yet')
        ->assertSee('Grant access');
});

it('paginates rather than rendering every row', function (): void {
    // NFR-PERF-02: there is no "show all" mode anywhere in this system.
    $this->actingAs($this->admin);

    $course = Course::factory()->create();
    foreach (range(1, 20) as $ignored) {
        app(GrantEnrollment::class)->handle(
            student: User::factory()->create(),
            course: $course,
            source: EnrollmentSource::AdminGrant,
            actor: $this->admin,
        );
    }

    Livewire::test(EnrollmentsTable::class)
        ->assertViewHas('enrollments', fn ($rows): bool => $rows->count() === 15 && $rows->total() === 21);
});
