<?php

declare(strict_types=1);

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\Enrollments\GrantEnrollmentForm;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\EnrollmentGrantedNotification;
use App\Services\Enrollment\EnrollmentAccessService;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Phase 6 UI — grant access directly (FR-ENR-06, Rule 1)
|--------------------------------------------------------------------------
|
| This form is the SECOND of only two ways access is ever created; the first
| is a signature-verified payment webhook. Both go through the same
| single-owner action, so the tests here are about the guards around that
| call — who may make it, and what it refuses — rather than the enrolment
| mechanics, which are Track A's to prove.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create(['name' => 'Asha Rao']);
    $this->course = Course::factory()->create(['title' => 'Advanced Widgetry']);
});

/*
| ═════════════ AUTHORISATION ═════════════
*/
it('refuses a student', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(GrantEnrollmentForm::class)->assertForbidden();
});

it('refuses an instructor', function (): void {
    // An instructor teaches a course; they do not decide who has paid for it.
    $this->actingAs(User::factory()->instructor()->create());

    Livewire::test(GrantEnrollmentForm::class)->assertForbidden();
});

it('re-authorises on submit, not only on mount', function (): void {
    /*
     * mount() gates the page; save() gates the mutation. Both matter: a
     * session whose role is downgraded while the form is open must not still
     * be able to grant access from a page it legitimately loaded.
     *
     * Modelled by mounting as an admin, then demoting that same user before
     * submitting.
     */
    $this->actingAs($this->admin);

    $component = Livewire::test(GrantEnrollmentForm::class)
        ->set('studentId', (string) $this->student->id)
        ->set('courseId', (string) $this->course->id)
        ->set('reason', 'Role changed mid-form');

    $this->admin->forceFill(['role' => UserRole::Student])->save();

    $component->call('save')->assertForbidden();

    expect(Enrollment::query()->count())->toBe(0);
});

/*
| ═════════════ VALIDATION ═════════════
*/
it('requires a student, a course and a reason', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(GrantEnrollmentForm::class)
        ->call('save')
        ->assertHasErrors(['studentId', 'courseId', 'reason']);

    expect(Enrollment::query()->count())->toBe(0);
});

it('refuses an end date in the past', function (): void {
    // An enrolment that expired yesterday grants nothing, and would look like
    // a bug rather than a choice.
    $this->actingAs($this->admin);

    Livewire::test(GrantEnrollmentForm::class)
        ->set('studentId', (string) $this->student->id)
        ->set('courseId', (string) $this->course->id)
        ->set('expiresAt', now()->subDay()->format('Y-m-d'))
        ->set('reason', 'Scholarship')
        ->call('save')
        ->assertHasErrors('expiresAt');
});

it('refuses to enrol a non-student account', function (): void {
    /*
     * The select only lists students, but the submitted id is attacker
     * controlled — rendering a control never implies permission (Rule 20).
     * Enrolling an admin would give them a student's access surface.
     */
    $this->actingAs($this->admin);

    $instructor = User::factory()->instructor()->create();

    Livewire::test(GrantEnrollmentForm::class)
        ->set('studentId', (string) $instructor->id)
        ->set('courseId', (string) $this->course->id)
        ->set('reason', 'Should not be possible')
        ->call('save')
        ->assertHasErrors('studentId');

    expect(Enrollment::query()->count())->toBe(0);
});

/*
| ═════════════ THE HAPPY PATH ═════════════
*/
it('grants access, records the source and actor, audits it, and emails', function (): void {
    Notification::fake();
    $this->actingAs($this->admin);

    Livewire::test(GrantEnrollmentForm::class)
        ->set('studentId', (string) $this->student->id)
        ->set('courseId', (string) $this->course->id)
        ->set('reason', 'Scholarship place')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.enrollments.index'));

    $enrollment = Enrollment::query()->firstOrFail();

    expect($enrollment->status)->toBe(EnrollmentStatus::Active)
        // FR-ENR-06: an admin grant must be distinguishable from a purchase.
        ->and($enrollment->source)->toBe(EnrollmentSource::AdminGrant)
        ->and(app(EnrollmentAccessService::class)->grantsAccess($this->student, $this->course))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'enrollment.granted')->exists())->toBeTrue();

    Notification::assertSentTo($this->student, EnrollmentGrantedNotification::class);
});

it('stores an optional end date', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(GrantEnrollmentForm::class)
        ->set('studentId', (string) $this->student->id)
        ->set('courseId', (string) $this->course->id)
        ->set('expiresAt', now()->addMonths(6)->format('Y-m-d'))
        ->set('reason', 'Six-month trial access')
        ->call('save')
        ->assertHasNoErrors();

    expect(Enrollment::query()->firstOrFail()->expires_at)->not->toBeNull();
});

it('sends one email when the same grant is submitted twice', function (): void {
    /*
     * Idempotency belongs to GrantEnrollment, not to this form — but a
     * double-submitted form is exactly how a real administrator would meet it,
     * so it is worth proving through the UI.
     */
    Notification::fake();
    $this->actingAs($this->admin);

    foreach (range(1, 2) as $ignored) {
        Livewire::test(GrantEnrollmentForm::class)
            ->set('studentId', (string) $this->student->id)
            ->set('courseId', (string) $this->course->id)
            ->set('reason', 'Scholarship place')
            ->call('save')
            ->assertHasNoErrors();
    }

    expect(Enrollment::query()->count())->toBe(1);
    Notification::assertSentToTimes($this->student, EnrollmentGrantedNotification::class, 1);
});

it('grants access to a draft course', function (): void {
    /*
     * Deliberate: giving a reviewer access to an unpublished course before
     * launch is one of the main reasons this screen exists. Publication
     * controls whether a course can be BOUGHT — a different question.
     */
    $this->actingAs($this->admin);

    $draft = Course::factory()->create(['title' => 'Unreleased Course']);

    Livewire::test(GrantEnrollmentForm::class)
        ->set('studentId', (string) $this->student->id)
        ->set('courseId', (string) $draft->id)
        ->set('reason', 'Pre-launch review')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(EnrollmentAccessService::class)->grantsAccess($this->student, $draft))->toBeTrue();
});

/*
| ═════════════ EMPTY STATE ═════════════
*/
it('explains itself when there is no student to enrol', function (): void {
    // UI-GUIDE.md §11: a form that cannot be completed should say so before it
    // is filled in, not after a failed submit.
    $this->actingAs($this->admin);
    User::query()->whereKeyNot($this->admin->getKey())->delete();

    Livewire::test(GrantEnrollmentForm::class)
        ->assertSee('No students to enrol')
        ->assertSee('Add a student');
});
