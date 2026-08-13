<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Actions\Enrollment\RevokeEnrollment;
use App\Enums\EmailStatus;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\EmailLog;
use App\Models\User;
use App\Notifications\EnrollmentGrantedNotification;
use App\Notifications\EnrollmentRevokedNotification;
use App\Services\Enrollment\EnrollmentAccessService;
use App\Services\Settings\SettingsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Phase 11 — enrollment mail, driven end to end (FR-MAIL-07, AC-33)
|--------------------------------------------------------------------------
|
| These do NOT dispatch the event by hand. They call Track A's real actions —
| GrantEnrollment and RevokeEnrollment — and assert that an email results.
|
| That distinction matters. Firing the event directly would prove my listener
| works while saying nothing about whether it is ever REACHED. The wiring
| between the action, the event and the listener is exactly the part that
| breaks silently, and it is the part these cover.
|
*/

beforeEach(function (): void {
    app(SettingsRepository::class)->set('branding.organisation_name', 'Distinctive Academy', 'branding');

    $this->student = User::factory()->create(['name' => 'Test Student']);
    $this->course = Course::factory()->create(['title' => 'Advanced Widgetry']);
    $this->admin = User::factory()->superAdmin()->create();
});

/*
| ═════════════ GRANT ═════════════
*/
it('emails the student when an enrollment is granted', function (): void {
    Notification::fake();

    app(GrantEnrollment::class)->handle(
        student: $this->student,
        course: $this->course,
        source: EnrollmentSource::AdminGrant,
        actor: $this->admin,
    );

    Notification::assertSentTo(
        $this->student,
        EnrollmentGrantedNotification::class,
    );
});

it('sends exactly one email when the same grant is replayed', function (): void {
    /*
     * THE WEBHOOK-RETRY CASE, which is the whole reason GrantEnrollment is
     * idempotent. Razorpay retries; the reconciliation job re-checks; an admin
     * double-clicks. Idempotency that stopped at the database row would still
     * email the student three times, and the student would reasonably conclude
     * they had been charged three times.
     */
    Notification::fake();

    foreach (range(1, 3) as $ignored) {
        app(GrantEnrollment::class)->handle(
            student: $this->student,
            course: $this->course,
            source: EnrollmentSource::AdminGrant,
            actor: $this->admin,
        );
    }

    Notification::assertSentToTimes($this->student, EnrollmentGrantedNotification::class, 1);
});

it('names the course in the granted email', function (): void {
    Notification::fake();

    app(GrantEnrollment::class)->handle(
        student: $this->student,
        course: $this->course,
        source: EnrollmentSource::AdminGrant,
        actor: $this->admin,
    );

    Notification::assertSentTo($this->student, EnrollmentGrantedNotification::class,
        function (EnrollmentGrantedNotification $notification): bool {
            $rendered = (string) $notification->toMail($this->student)->render();

            // The student must be able to tell WHICH course without opening the app.
            return str_contains($rendered, 'Advanced Widgetry')
                && str_contains($rendered, 'Distinctive Academy');
        });
});

/*
| ═════════════ REVOKE ═════════════
*/
it('emails the student when an enrollment is revoked', function (): void {
    Notification::fake();

    $enrollment = app(GrantEnrollment::class)->handle(
        student: $this->student,
        course: $this->course,
        source: EnrollmentSource::AdminGrant,
        actor: $this->admin,
    );

    app(RevokeEnrollment::class)->handle(
        enrollment: $enrollment,
        actor: $this->admin,
        reason: 'Refund processed.',
    );

    Notification::assertSentTo($this->student, EnrollmentRevokedNotification::class);
});

it('carries the reason into the revoked email', function (): void {
    Notification::fake();

    $enrollment = app(GrantEnrollment::class)->handle(
        student: $this->student,
        course: $this->course,
        source: EnrollmentSource::AdminGrant,
        actor: $this->admin,
    );

    app(RevokeEnrollment::class)->handle(
        enrollment: $enrollment,
        actor: $this->admin,
        reason: 'Refund processed.',
    );

    Notification::assertSentTo($this->student, EnrollmentRevokedNotification::class,
        function (EnrollmentRevokedNotification $notification): bool {
            // A student told only "your access has ended" will ask why. The
            // email answers before they have to.
            return str_contains((string) $notification->toMail($this->student)->render(), 'Refund processed.');
        });
});

it('emails the student when a time-limited enrollment expires on schedule', function (): void {
    /*
     * THE THIRD ROUTE TO LOSING ACCESS, and the one most likely to break
     * silently: nobody is watching when a nightly command runs. An expiry that
     * removed access without telling the student would look, from their side,
     * exactly like the site being broken.
     *
     * Driven through the real scheduled command rather than the event.
     */
    Notification::fake();

    $enrollment = app(GrantEnrollment::class)->handle(
        student: $this->student,
        course: $this->course,
        source: EnrollmentSource::AdminGrant,
        actor: $this->admin,
        expiresAt: CarbonImmutable::now()->subDay(),
    );

    // Exit code rather than assertSuccessful(): Artisan::call returns an int
    // here, and asserting on it says the same thing without pretending
    // otherwise.
    expect(Artisan::call('lms:enrollments:expire'))->toBe(0);

    Notification::assertSentTo($this->student, EnrollmentRevokedNotification::class,
        function (EnrollmentRevokedNotification $notification): bool {
            $rendered = (string) $notification->toMail($this->student)->render();

            /*
             * Automatic expiry must not read as though a person intervened.
             * "An administrator has withdrawn your access" is alarming and
             * untrue when a time limit simply elapsed.
             */
            return str_contains($rendered, 'access period')
                && ! str_contains($rendered, 'has been withdrawn');
        });

    expect($enrollment->refresh()->status)->not->toBe(EnrollmentStatus::Active);
});

/*
| ═════════════ AC-33: MAIL NEVER BREAKS THE ENROLLMENT ═════════════
*/
it('completes the grant even though the email is only queued', function (): void {
    /*
     * The customer's first rule, in miniature: access comes from the verified
     * grant, never from the email that announces it. The enrollment must exist
     * and be active regardless of what happens to the mail.
     */
    Notification::fake();

    $enrollment = app(GrantEnrollment::class)->handle(
        student: $this->student,
        course: $this->course,
        source: EnrollmentSource::AdminGrant,
        actor: $this->admin,
    );

    // Access is asserted through EnrollmentAccessService, the single
    // definition of "has access" in this system (rule S-8) — never an ad-hoc
    // status check that could drift from it.
    expect($enrollment->exists)->toBeTrue()
        ->and(app(EnrollmentAccessService::class)->grantsAccess($this->student, $this->course))->toBeTrue();
});

it('queues enrollment mail rather than sending it in the request', function (): void {
    /*
     * Proven against the real queue rather than a fake, because after_commit
     * is the property under test and Queue::fake() ignores it. Nothing may be
     * enqueued until the enrollment transaction has committed.
     */
    config()->set('queue.default', 'database');
    DB::table('jobs')->delete();

    DB::transaction(function (): void {
        app(GrantEnrollment::class)->handle(
            student: $this->student,
            course: $this->course,
            source: EnrollmentSource::AdminGrant,
            actor: $this->admin,
        );

        expect(DB::table('jobs')->count())->toBe(0);
    });

    expect(DB::table('jobs')->count())->toBeGreaterThan(0);
});

/*
| ═════════════ LOGGED LIKE EVERY OTHER EMAIL (FR-MAIL-10) ═════════════
*/
it('records the enrollment email in email_logs', function (): void {
    app(GrantEnrollment::class)->handle(
        student: $this->student,
        course: $this->course,
        source: EnrollmentSource::AdminGrant,
        actor: $this->admin,
    );

    $log = EmailLog::query()
        ->where('mailable', EnrollmentGrantedNotification::class)
        ->latest('id')
        ->firstOrFail();

    expect($log->to_email)->toBe($this->student->email)
        ->and($log->status)->toBe(EmailStatus::Sent)
        ->and($log->subject)->toContain('Advanced Widgetry');
});
