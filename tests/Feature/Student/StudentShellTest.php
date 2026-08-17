<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\CourseLevel;
use App\Enums\EnrollmentSource;
use App\Livewire\Catalogue\Index as CatalogueIndex;
use App\Livewire\Student\MyCourses;
use App\Models\Course;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The student shell and the screens rebuilt against `sample student ui/`
|--------------------------------------------------------------------------
|
| Covers the behaviour the redesign introduced, not its appearance. Three things
| here are genuinely new and could break silently:
|
|   the My Learning tabs, which filter a list in PHP rather than re-querying;
|   the catalogue's level filter, which must ignore junk from the URL;
|   the shared header, which a signed-in student must keep on the PUBLIC
|   catalogue — that one is a cross-layout include and exactly the kind of thing
|   that regresses when either layout is edited.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->student()->create([
        'first_name' => 'Priya',
        'last_name' => 'Sharma',
        'name' => 'Priya Sharma',
    ]);

    $this->enrol = fn (User $u, Course $c) => app(GrantEnrollment::class)
        ->handle($u, $c, EnrollmentSource::AdminGrant, $this->admin);
});

/*
| ═══════════════ THE AVATAR DISC ═══════════════
*/
it('builds initials from the two name parts', function (): void {
    expect($this->student->initials())->toBe('PS');
});

it('falls back to the display name when no parts are recorded', function (): void {
    $user = User::factory()->student()->create([
        'name' => 'Ravi Menon',
        'first_name' => null,
        'last_name' => null,
    ]);

    // Split from `name`, so an account predating those columns still gets a
    // sensible disc rather than a blank one.
    expect($user->initials())->toBe('RM');
});

it('gives a single-word name one letter rather than doubling it', function (): void {
    $user = User::factory()->student()->create(['name' => 'Prince', 'first_name' => null, 'last_name' => null]);

    expect($user->initials())->toBe('P');
});

it('never renders an empty disc', function (): void {
    // Pathological, but an empty disc reads as a rendering failure, and this is
    // the one place a placeholder beats nothing.
    $user = User::factory()->student()->create(['name' => '...', 'first_name' => null, 'last_name' => null]);

    expect($user->initials())->not->toBe('');
});

/*
| ═══════════════ THE SHARED HEADER ═══════════════
|
| The catalogue is a public page AND the "Explore" tab of the student shell.
| A student browsing it must keep their navigation — swapping them back to the
| guest header mid-session would read as having been signed out.
*/
it('keeps a signed-in student on their own header while browsing the catalogue', function (): void {
    $this->actingAs($this->student)
        ->get(route('catalogue.index'))
        ->assertOk()
        ->assertSee('My Learning')
        ->assertSee('Your profile')
        // And NOT the guest header's way in.
        ->assertDontSee('Sign in');
});

it('shows a guest the public header on the same page', function (): void {
    $this->get(route('catalogue.index'))
        ->assertOk()
        ->assertSee('Sign in')
        ->assertDontSee('My Learning');
});

it('does not put the student nav in front of an instructor', function (): void {
    // An instructor has no My Learning list, so those links would lead
    // somewhere they cannot go.
    $this->actingAs(User::factory()->instructor()->create())
        ->get(route('catalogue.index'))
        ->assertOk()
        ->assertDontSee('My Learning');
});

it('omits the features that do not exist yet', function (): void {
    /*
     * The mockup's header carries a Certificates tab and a notifications bell.
     * Neither feature exists — no certificate model, migration or issuing rule
     * anywhere, and no notification centre. A tab leading to an empty screen
     * promises something the product cannot do (Rule 5 — do not build ahead).
     *
     * Pinned as a test because "add the missing nav item" is a tempting and
     * wrong five-second fix for someone comparing the screen to the mockup.
     */
    $this->actingAs($this->student)
        ->get(route('student.home'))
        ->assertOk()
        ->assertDontSee('Certificates');
});

/*
| ═══════════════ MY LEARNING TABS ═══════════════
*/
it('shows every accessible course on the All tab', function (): void {
    ($this->enrol)($this->student, Course::factory()->published()->create(['title' => 'Started Course']));
    ($this->enrol)($this->student, Course::factory()->published()->create(['title' => 'Finished Course']));

    $this->actingAs($this->student);

    Livewire::test(MyCourses::class)
        ->assertSee('Started Course')
        ->assertSee('Finished Course');
});

it('separates in-progress from completed', function (): void {
    ($this->enrol)($this->student, Course::factory()->published()->create(['title' => 'Started Course']));

    $finished = ($this->enrol)($this->student, Course::factory()->published()->create(['title' => 'Finished Course']));
    $finished->forceFill(['completed_at' => now()])->save();

    $this->actingAs($this->student);

    Livewire::test(MyCourses::class)
        ->set('filter', 'in-progress')
        ->assertSee('Started Course')
        ->assertDontSee('Finished Course')
        ->set('filter', 'completed')
        ->assertSee('Finished Course')
        ->assertDontSee('Started Course');
});

it('keys completed on the enrollment, not on the percentage', function (): void {
    /*
     * A course can read 100% while its final test is still outstanding. Calling
     * that finished would tell a student they had earned something they had not
     * (ADR-008 — the enrollment row is the fact, the figure is a cache).
     */
    $enrollment = ($this->enrol)($this->student, Course::factory()->published()->create(['title' => 'All Lessons Ticked']));
    $enrollment->forceFill(['progress_percentage' => 100, 'completed_at' => null])->save();

    $this->actingAs($this->student);

    Livewire::test(MyCourses::class)
        ->set('filter', 'completed')
        ->assertDontSee('All Lessons Ticked')
        ->set('filter', 'in-progress')
        ->assertSee('All Lessons Ticked');
});

it('says which kind of nothing an empty tab is', function (): void {
    // "You have no courses" and "none of yours are finished yet" are different
    // facts; showing the first when the second is true reads as the product
    // having lost them.
    ($this->enrol)($this->student, Course::factory()->published()->create());

    $this->actingAs($this->student);

    Livewire::test(MyCourses::class)
        ->set('filter', 'completed')
        ->assertSee('have not finished a course yet')
        ->assertDontSee('Nothing here yet');
});

it('falls through to everything for an unrecognised tab', function (): void {
    // ?show=nonsense must not present an empty library.
    ($this->enrol)($this->student, Course::factory()->published()->create(['title' => 'Real Course']));

    $this->actingAs($this->student);

    Livewire::test(MyCourses::class)
        ->set('filter', 'nonsense')
        ->assertSee('Real Course');
});

/*
| ═══════════════ THE CATALOGUE LEVEL FILTER ═══════════════
*/
it('narrows the catalogue by level', function (): void {
    Course::factory()->published()->create(['title' => 'Easy Course', 'level' => CourseLevel::Beginner]);
    Course::factory()->published()->create(['title' => 'Hard Course', 'level' => CourseLevel::Advanced]);

    Livewire::test(CatalogueIndex::class)
        ->set('level', CourseLevel::Beginner->value)
        ->assertSee('Easy Course')
        ->assertDontSee('Hard Course');
});

it('ignores a level that is not a real one', function (): void {
    // ?level=' OR 1=1 must narrow to nothing recognised rather than reach the
    // query. CourseLevel::tryFrom returns null for junk.
    Course::factory()->published()->create(['title' => 'Easy Course', 'level' => CourseLevel::Beginner]);

    Livewire::test(CatalogueIndex::class)
        ->set('level', "' OR 1=1")
        ->assertSee('Easy Course');
});

it('combines level with a search term', function (): void {
    Course::factory()->published()->create(['title' => 'Python Basics', 'level' => CourseLevel::Beginner]);
    Course::factory()->published()->create(['title' => 'Python Advanced', 'level' => CourseLevel::Advanced]);
    Course::factory()->published()->create(['title' => 'Ruby Basics', 'level' => CourseLevel::Beginner]);

    Livewire::test(CatalogueIndex::class)
        ->set('search', 'Python')
        ->set('level', CourseLevel::Beginner->value)
        ->assertSee('Python Basics')
        ->assertDontSee('Python Advanced')
        ->assertDontSee('Ruby Basics');
});

/*
| ═══════════════ NO INVENTED FIGURES ═══════════════
*/
it('never prints a rating or a learner count', function (): void {
    /*
     * The mockup shows "★ 4.8 (2,340 ratings)" and "12,480 learners". Neither
     * exists in this schema. On a page whose job is to sell a course, inventing
     * social proof is not a placeholder — it is a false claim, and it is the
     * kind of thing that gets copied forward once it ships.
     */
    $course = Course::factory()->published()->create();

    $this->get(route('catalogue.show', $course))
        ->assertOk()
        ->assertDontSee('ratings')
        ->assertDontSee('learners');
});

it('never claims a certificate the system cannot issue', function (): void {
    ($this->enrol)($this->student, Course::factory()->published()->create());

    $this->actingAs($this->student)
        ->get(route('student.home'))
        ->assertOk()
        ->assertDontSee('Certificates earned');
});
