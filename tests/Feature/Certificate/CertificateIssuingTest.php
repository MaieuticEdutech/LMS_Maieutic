<?php

declare(strict_types=1);

use App\Actions\Certificate\IssueCertificate;
use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Events\CourseCompleted;
use App\Listeners\IssueCertificateOnCourseCompletion;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Exceptions;

/*
|--------------------------------------------------------------------------
| Awarding a certificate (design handoff §7, FR-PROG-08, AC-31)
|--------------------------------------------------------------------------
|
| A certificate is a claim the organisation makes about a person. Three
| properties carry the weight:
|
|   it is issued ONLY for a genuinely completed enrolment — not for a course
|   sitting at 100% with its final test outstanding;
|
|   it is issued ONCE, however many times completion is recalculated;
|
|   the name and course title on it are SNAPSHOTS, so editing a profile or
|   renaming a course cannot rewrite a document someone already verified.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();

    $this->student = User::factory()->create([
        'name' => 'Priya Sharma',
        'first_name' => 'Priya',
        'last_name' => 'Sharma',
        'certificate_name' => null,
    ]);

    $this->course = Course::factory()->published()->create(['title' => 'Python for Data Science']);

    $this->enrollment = app(GrantEnrollment::class)
        ->handle($this->student, $this->course, EnrollmentSource::AdminGrant, $this->admin);

    $this->complete = function (): void {
        $this->enrollment->forceFill([
            'status' => EnrollmentStatus::Completed,
            'completed_at' => now(),
        ])->save();
    };
});

/*
| ═══════════════ ONLY FOR A REAL COMPLETION ═══════════════
*/
it('refuses to award one for an unfinished enrolment', function (): void {
    expect(fn () => app(IssueCertificate::class)->handle($this->enrollment))
        ->toThrow(RuntimeException::class);

    expect(Certificate::query()->count())->toBe(0);
});

it('refuses even when the progress figure reads 100%', function (): void {
    /*
     * THE FIGURE IS A CACHE; THE ENROLMENT IS THE FACT (ADR-008, AC-31).
     *
     * A course requiring a final test sits at 100% with every lesson ticked
     * while the test is still outstanding. Awarding on the percentage would
     * hand out a credential nobody had earned — which is the single most
     * expensive thing this table could get wrong.
     */
    $this->enrollment->forceFill(['progress_percentage' => 100, 'completed_at' => null])->save();

    expect(fn () => app(IssueCertificate::class)->handle($this->enrollment))
        ->toThrow(RuntimeException::class);
});

it('awards one for a completed enrolment', function (): void {
    ($this->complete)();

    $certificate = app(IssueCertificate::class)->handle($this->enrollment->refresh());

    expect($certificate->exists)->toBeTrue()
        ->and($certificate->user_id)->toBe($this->student->getKey())
        ->and($certificate->course_id)->toBe($this->course->getKey());
});

/*
| ═══════════════ ISSUED ONCE ═══════════════
*/
it('returns the same certificate rather than awarding a second', function (): void {
    ($this->complete)();
    $enrollment = $this->enrollment->refresh();

    $first = app(IssueCertificate::class)->handle($enrollment);
    $second = app(IssueCertificate::class)->handle($enrollment);

    expect($second->getKey())->toBe($first->getKey())
        ->and(Certificate::query()->count())->toBe(1);
});

it('keeps one certificate per enrolment at the database level', function (): void {
    ($this->complete)();
    app(IssueCertificate::class)->handle($this->enrollment->refresh());

    // The action's own check is a read followed by a write, which is the
    // definition of a race. The unique index is what makes the rule true.
    expect(fn () => Certificate::factory()->create([
        'enrollment_id' => $this->enrollment->getKey(),
        'user_id' => $this->student->getKey(),
        'course_id' => $this->course->getKey(),
    ]))->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

/*
| ═══════════════ THE TEXT IS A SNAPSHOT ═══════════════
*/
it('records the name the learner asked for on certificates', function (): void {
    $this->student->forceFill(['certificate_name' => 'Priya R. Sharma'])->save();
    ($this->complete)();

    $certificate = app(IssueCertificate::class)->handle($this->enrollment->refresh());

    expect($certificate->recipient_name)->toBe('Priya R. Sharma');
});

it('falls back to the display name when no preference was stated', function (): void {
    ($this->complete)();

    expect(app(IssueCertificate::class)->handle($this->enrollment->refresh())->recipient_name)
        ->toBe('Priya Sharma');
});

it('does not change when the learner later edits their profile', function (): void {
    ($this->complete)();
    $certificate = app(IssueCertificate::class)->handle($this->enrollment->refresh());

    // A year later, they change their name.
    $this->student->forceFill(['certificate_name' => 'Someone Else Entirely'])->save();

    // The award already verified by an employer must not have been rewritten.
    expect($certificate->refresh()->recipient_name)->toBe('Priya Sharma');
});

it('does not change when the course is renamed', function (): void {
    ($this->complete)();
    $certificate = app(IssueCertificate::class)->handle($this->enrollment->refresh());

    $this->course->forceFill(['title' => 'Python for Data Science (2027 edition)'])->save();

    expect($certificate->refresh()->course_title)->toBe('Python for Data Science');
});

it('dates the award from the completion, not from the moment the row was written', function (): void {
    // Matters for a backfill: awarding a historic completion today must not
    // print today's date on the document.
    $completedAt = now()->subMonths(3);
    $this->enrollment->forceFill([
        'status' => EnrollmentStatus::Completed,
        'completed_at' => $completedAt,
    ])->save();

    $certificate = app(IssueCertificate::class)->handle($this->enrollment->refresh());

    expect($certificate->issued_at->toDateString())->toBe($completedAt->toDateString());
});

/*
| ═══════════════ THE NUMBER ═══════════════
*/
it('generates a verifiable number in the documented format', function (): void {
    ($this->complete)();

    $number = app(IssueCertificate::class)->handle($this->enrollment->refresh())->number;

    expect(IssueCertificate::looksLikeCertificateNumber($number))->toBeTrue();
});

it('never uses characters that are misread off a printed page', function (): void {
    // The whole point of this string is that a human retypes it into a
    // verification box, so O/0, I/1, S/5 and B/8 are out of the alphabet.
    ($this->complete)();

    $number = app(IssueCertificate::class)->handle($this->enrollment->refresh())->number;

    expect(substr($number, 9))->not->toMatch('/[OI01S5B8]/');
});

it('gives every certificate a different number', function (): void {
    ($this->complete)();
    app(IssueCertificate::class)->handle($this->enrollment->refresh());

    $others = collect(range(1, 5))->map(function (): string {
        $student = User::factory()->create();
        $course = Course::factory()->published()->create();

        $enrollment = app(GrantEnrollment::class)
            ->handle($student, $course, EnrollmentSource::AdminGrant, $this->admin);

        $enrollment->forceFill(['status' => EnrollmentStatus::Completed, 'completed_at' => now()])->save();

        return app(IssueCertificate::class)->handle($enrollment->refresh())->number;
    });

    expect($others->unique())->toHaveCount(5);
});

/*
| ═══════════════ AUDITED ═══════════════
*/
it('records the award in the audit log', function (): void {
    ($this->complete)();
    app(IssueCertificate::class)->handle($this->enrollment->refresh());

    // "Who was awarded what, and when" is a question that gets asked, and a
    // credential that appeared with no trace is one nobody can investigate.
    expect(AuditLog::query()->where('action', 'certificate.issued')->exists())->toBeTrue();
});

/*
| ═══════════════ THE LISTENER ═══════════════
*/
it('awards a certificate when the course-completed event fires', function (): void {
    ($this->complete)();

    app(IssueCertificateOnCourseCompletion::class)->handle(
        new CourseCompleted($this->enrollment->refresh()),
    );

    expect(Certificate::query()->where('enrollment_id', $this->enrollment->getKey())->exists())->toBeTrue();
});

it('never lets a failed award break the completion itself', function (): void {
    /*
     * Finishing a course is the student's achievement; the certificate is a
     * consequence. If issuing throws — here, because the enrolment is not
     * actually complete — the listener must swallow it into the log rather than
     * letting it propagate and take the completion email with it.
     */
    Exceptions::fake();

    app(IssueCertificateOnCourseCompletion::class)->handle(
        new CourseCompleted($this->enrollment),
    );

    expect(Certificate::query()->count())->toBe(0);

    // Swallowed, but NOT silently. The whole risk of catching here is that a
    // missed award becomes invisible, so it is reported to the handler — which
    // is where error tracking is wired — rather than only written to a log file
    // nobody reads.
    Exceptions::assertReported(RuntimeException::class);
});
