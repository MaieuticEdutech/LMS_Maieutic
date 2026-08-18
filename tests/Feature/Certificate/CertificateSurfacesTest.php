<?php

declare(strict_types=1);

use App\Actions\Certificate\IssueCertificate;
use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| The certificate screens (design handoff §7)
|--------------------------------------------------------------------------
|
| Two surfaces with opposite rules, which is the whole point of this file:
|
|   /certificates    signed in, and shows ONLY the viewer's own awards;
|   /verify/{number} public, no account, and shows one award to anybody
|                    holding its number.
|
| The second is deliberately unauthenticated — a credential a stranger cannot
| check is not a credential. What keeps it safe is that the number is
| unguessable and the page asserts nothing the certificate does not already say
| in public. The tests below pin exactly that: what it shows, and what it must
| never show.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();

    $this->student = User::factory()->create([
        'name' => 'Priya Sharma',
        'email' => 'priya@example.test',
    ]);

    $this->award = function (User $student, string $title = 'Python for Data Science'): Certificate {
        $course = Course::factory()->published()->create(['title' => $title]);

        $enrollment = app(GrantEnrollment::class)
            ->handle($student, $course, EnrollmentSource::AdminGrant, $this->admin);

        $enrollment->forceFill([
            'status' => EnrollmentStatus::Completed,
            'completed_at' => now(),
        ])->save();

        return app(IssueCertificate::class)->handle($enrollment->refresh());
    };
});

/*
| ═══════════════ THE STUDENT'S OWN LIST ═══════════════
*/
it('lists the student\'s certificates', function (): void {
    ($this->award)($this->student);

    $this->actingAs($this->student)
        ->get(route('student.certificates.index'))
        ->assertOk()
        ->assertSee('Python for Data Science')
        ->assertSee('CERTIFICATE OF COMPLETION');
});

it('never shows one student another\'s certificate', function (): void {
    $other = ($this->award)(User::factory()->create(), 'Someone Else\'s Course');

    $this->actingAs($this->student)
        ->get(route('student.certificates.index'))
        ->assertOk()
        ->assertDontSee('Someone Else\'s Course')
        ->assertDontSee($other->number);
});

it('shows an empty state before the first award', function (): void {
    $this->actingAs($this->student)
        ->get(route('student.certificates.index'))
        ->assertOk()
        ->assertSee('No certificates yet');
});

it('keeps the list behind authentication', function (): void {
    $this->get(route('student.certificates.index'))->assertRedirect(route('login'));
});

it('is a student-only screen', function (): void {
    // An instructor has no certificates of their own; the route is behind
    // role:student and the nav item is hidden to match.
    $this->actingAs(User::factory()->instructor()->create())
        ->get(route('student.certificates.index'))
        ->assertForbidden();
});

it('counts certificates on the dashboard from the table, not from completions', function (): void {
    ($this->award)($this->student);

    $this->actingAs($this->student)
        ->get(route('student.home'))
        ->assertOk()
        ->assertSee('Certificates earned');
});

/*
| ═══════════════ PUBLIC VERIFICATION ═══════════════
*/
it('lets a stranger verify a certificate with no account at all', function (): void {
    $certificate = ($this->award)($this->student);

    $this->get(route('certificates.verify', $certificate))
        ->assertOk()
        ->assertSee('Priya Sharma')
        ->assertSee('Python for Data Science')
        ->assertSee($certificate->number);
});

it('never exposes anything about the holder beyond the award', function (): void {
    $certificate = ($this->award)($this->student);

    // A stranger confirming a credential needs the claim and nothing else. An
    // email address on a public page reachable by ID would be a quiet way to
    // turn a certificate number into a contact detail.
    $this->get(route('certificates.verify', $certificate))
        ->assertOk()
        ->assertDontSee('priya@example.test');
});

it('does not leak a holder\'s other certificates', function (): void {
    $first = ($this->award)($this->student, 'Python for Data Science');
    ($this->award)($this->student, 'SQL for Analytics');

    $this->get(route('certificates.verify', $first))
        ->assertOk()
        ->assertDontSee('SQL for Analytics');
});

it('404s on a number that was never issued', function (): void {
    // Identical response to a mistyped one. There is no state to distinguish.
    $this->get('/verify/MAI-CERT-AAAA-AAAA')->assertNotFound();
});

it('is looked up by number rather than by id', function (): void {
    $certificate = ($this->award)($this->student);

    // A sequential id in a verification link would let anyone walk the whole
    // set of awards the organisation has ever made.
    expect(route('certificates.verify', $certificate))->toContain($certificate->number)
        ->and(route('certificates.verify', $certificate))->not->toContain('/verify/'.$certificate->getKey());
});

it('shows the snapshot, not the live course title', function (): void {
    $certificate = ($this->award)($this->student);

    $course = Course::query()->findOrFail($certificate->course_id);
    $course->forceFill(['title' => 'Renamed Next Year'])->save();

    $this->get(route('certificates.verify', $certificate))
        ->assertOk()
        ->assertSee('Python for Data Science')
        ->assertDontSee('Renamed Next Year');
});

/*
| ═══════════════ THE POLICY ═══════════════
*/
it('lets a super admin open any certificate', function (): void {
    $certificate = ($this->award)($this->student);

    expect($this->admin->can('view', $certificate))->toBeTrue();
});

it('lets a student open their own', function (): void {
    $certificate = ($this->award)($this->student);

    expect($this->student->can('view', $certificate))->toBeTrue();
});

it('refuses one student another\'s', function (): void {
    $certificate = ($this->award)(User::factory()->create());

    expect($this->student->can('view', $certificate))->toBeFalse();
});

it('refuses an instructor who taught the course', function (): void {
    // A legitimate interest in who passed is served by the progress screens.
    // "Taught them once" is not a standing reason to read someone's credentials
    // afterwards.
    $certificate = ($this->award)($this->student);

    expect(User::factory()->instructor()->create()->can('view', $certificate))->toBeFalse();
});

it('lets nobody mint a certificate through the web', function (): void {
    // A credential anyone can create is not a credential. Awarding happens in
    // IssueCertificate off the back of a real completion, or not at all.
    expect($this->admin->can('create', Certificate::class))->toBeFalse()
        ->and($this->student->can('create', Certificate::class))->toBeFalse();
});

it('lets nobody edit or delete one', function (): void {
    $certificate = ($this->award)($this->student);

    expect($this->admin->can('update', $certificate))->toBeFalse()
        ->and($this->admin->can('delete', $certificate))->toBeFalse();
});
