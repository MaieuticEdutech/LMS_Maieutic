<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Actions\Profile\ChangeEmail;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Livewire\Student\ProfileForm;
use App\Models\Course;
use App\Models\User;
use App\Services\Enrollment\EnrollmentAccessService;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Phase 7 · Dashboard, My Courses and profile (FR-STU-05, FR-STU-06, FR-STU-12)
|--------------------------------------------------------------------------
|
| The listing screens carry a rule that is easy to get subtly wrong: they must
| show exactly the courses the player would open, and nothing else. A course
| visible here but 403 when clicked reads as a broken product.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();
    $this->course = Course::factory()->published()->create(['title' => 'Visible Course']);

    $this->enrol = fn (User $u, Course $c) => app(GrantEnrollment::class)
        ->handle($u, $c, EnrollmentSource::AdminGrant, $this->admin);
});

it('shows the empty state to a student with no enrollments', function (): void {
    // The first thing every account sees on day one.
    $this->actingAs($this->student)
        ->get(route('student.home'))
        ->assertOk()
        ->assertSee('not enrolled in any courses');
});

it('lists an enrolled course', function (): void {
    ($this->enrol)($this->student, $this->course);

    $this->actingAs($this->student)
        ->get(route('student.home'))
        ->assertOk()
        ->assertSee('Visible Course');
});

it('never lists another student\'s courses as enrolled', function (): void {
    ($this->enrol)(User::factory()->create(), $this->course);

    /*
     * NARROWED WHEN "Recommended for you" LANDED, and worth being precise
     * about why.
     *
     * This used to assert the title was absent from the dashboard entirely.
     * The redesign adds a recommendations section listing PUBLISHED courses
     * the student is not enrolled in — so a published course now legitimately
     * appears on every dashboard, including this one.
     *
     * That is not the leak this test exists to catch. The course's own
     * metadata is already public at /courses (AC-01), and nothing about the
     * other student's enrollment is exposed. What must never happen is this
     * student's dashboard treating it as THEIRS, so the assertion is now that
     * they still have no enrollments at all.
     */
    $this->actingAs($this->student)
        ->get(route('student.home'))
        ->assertOk()
        ->assertSee('not enrolled in any courses');
});

/*
| ═══════════ THE LIST MUST MATCH WHAT THE PLAYER WILL OPEN ═══════════
|
| A course listed but unopenable is worse than one that is absent: the student
| clicks, gets 403, and concludes the product is broken rather than that their
| access ended.
*/
it('hides courses whose access has ended', function (EnrollmentStatus $status): void {
    $enrollment = ($this->enrol)($this->student, $this->course);
    $enrollment->forceFill(['status' => $status])->save();
    app(EnrollmentAccessService::class)->flush();

    $this->actingAs($this->student)
        ->get(route('student.courses.index'))
        ->assertOk()
        ->assertDontSee('Visible Course');
})->with([
    'suspended' => EnrollmentStatus::Suspended,
    'expired' => EnrollmentStatus::Expired,
    'refunded' => EnrollmentStatus::Refunded,
]);

it('still lists a completed course', function (): void {
    // Finishing does not end access, so it must not vanish from the library.
    $enrollment = ($this->enrol)($this->student, $this->course);
    $enrollment->forceFill(['status' => EnrollmentStatus::Completed, 'completed_at' => now()])->save();
    app(EnrollmentAccessService::class)->flush();

    $this->actingAs($this->student)
        ->get(route('student.courses.index'))
        ->assertOk()
        ->assertSee('Visible Course');
});

it('hides a course whose expiry has passed before the scheduler has run', function (): void {
    // The status column still says active. The listing must agree with the
    // player, which evaluates the date rather than trusting the column.
    $enrollment = ($this->enrol)($this->student, $this->course);
    $enrollment->forceFill(['expires_at' => now()->subMinute()])->save();
    app(EnrollmentAccessService::class)->flush();

    expect($enrollment->refresh()->status)->toBe(EnrollmentStatus::Active);

    $this->actingAs($this->student)
        ->get(route('student.courses.index'))
        ->assertOk()
        ->assertDontSee('Visible Course');
});

/*
| ═══════════════ PROFILE ═══════════════
*/
it('updates name and phone without asking for a password', function (): void {
    $this->actingAs($this->student);

    // The name is edited as two parts now; `name` is projected from them.
    // ProfileDetailsTest covers that projection in detail — what matters here is
    // that this edit still needs no password, unlike an email change.
    Livewire::test(ProfileForm::class)
        ->set('firstName', 'Updated')
        ->set('lastName', 'Name')
        ->set('phone', '+91 90000 00000')
        ->call('saveDetails')
        ->assertHasNoErrors();

    expect($this->student->refresh()->name)->toBe('Updated Name')
        ->and($this->student->phone)->toBe('+91 90000 00000');
});

it('refuses an email change without the current password', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('email', 'new@example.com')
        ->set('currentPassword', 'wrong-password')
        ->call('saveEmail')
        ->assertHasErrors('currentPassword');

    // Unchanged, and still verified.
    expect($this->student->refresh()->email)->not->toBe('new@example.com')
        ->and($this->student->email_verified_at)->not->toBeNull();
});

it('changes the email and clears verification when the password is right', function (): void {
    Notification::fake();

    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('email', 'new@example.com')
        ->set('currentPassword', 'password')
        ->call('saveEmail')
        ->assertHasNoErrors();

    $fresh = $this->student->refresh();

    expect($fresh->email)->toBe('new@example.com')
        // Unverified until they open the link — that is what the change means.
        ->and($fresh->email_verified_at)->toBeNull()
        // Status untouched: a profile edit must never revoke paid access.
        ->and($fresh->status)->toBe($this->student->status);
});

it('never leaves the submitted password in component state', function (): void {
    // Livewire serialises public properties into the page. A password left
    // there would be readable in the DOM and replayable on the next request.
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('email', 'new@example.com')
        ->set('currentPassword', 'wrong-password')
        ->call('saveEmail')
        ->assertSet('currentPassword', '');
});

it('refuses an email already used by someone else', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('email', 'taken@example.com')
        ->set('currentPassword', 'password')
        ->call('saveEmail')
        ->assertHasErrors('email');
});

it('rejects an unchanged email rather than re-sending verification', function (): void {
    expect(fn () => app(ChangeEmail::class)->handle($this->student, $this->student->email))
        ->toThrow(InvalidArgumentException::class);
});
