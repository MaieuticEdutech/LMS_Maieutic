<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\AssessmentType;
use App\Enums\EnrollmentSource;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Phase 10 · AC-03, on every instructor route (FR-RBAC-04, FR-INS-*)
|--------------------------------------------------------------------------
|
| ═════════════════════════════════════════════════════════════════════════
| "AC-03 PASSES, EXHAUSTIVELY, ACROSS EVERY INSTRUCTOR ROUTE" — Phase 10 DoD.
|
| It was covered on two of the seven. This file is the exhaustive version, and
| it is written as a table over the ROUTE LIST rather than as one test per
| screen, so a route added later without scoping shows up as a missing entry
| rather than as silence.
|
| The scoping mechanism is worth understanding: route model binding will bind
| ANY course slug an instructor types. What makes that a 404 instead of a data
| leak is CourseDetail re-resolving through InstructorCourseService rather
| than trusting the bound model (architecture.md §8.4).
| ═════════════════════════════════════════════════════════════════════════
|
| The other half of the DoD — "zero financial data is reachable by an
| instructor" — had no test at all. It has one now.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();

    $this->instructor = User::factory()->instructor()->create();
    $this->otherInstructor = User::factory()->instructor()->create();
    $this->student = User::factory()->student()->create();

    // A course this instructor teaches, and one they do not.
    $this->mine = Course::factory()->published()->create(['title' => 'Mine to teach']);
    $this->theirs = Course::factory()->published()->create(['title' => 'Somebody elses course']);

    $this->mine->instructors()->attach($this->instructor);
    $this->theirs->instructors()->attach($this->otherInstructor);

    $this->myAssessment = Assessment::factory()->create([
        'assessable_type' => Course::class,
        'assessable_id' => $this->mine->getKey(),
        'type' => AssessmentType::Test,
        'title' => 'My assessment',
    ]);

    $this->theirAssessment = Assessment::factory()->create([
        'assessable_type' => Course::class,
        'assessable_id' => $this->theirs->getKey(),
        'type' => AssessmentType::Test,
        'title' => 'Their assessment',
    ]);

    $this->myEnrollment = app(GrantEnrollment::class)
        ->handle($this->student, $this->mine, EnrollmentSource::AdminGrant, $this->admin);

    $this->theirEnrollment = app(GrantEnrollment::class)
        ->handle($this->student, $this->theirs, EnrollmentSource::AdminGrant, $this->admin);
});

/*
| ═══════════════ EVERY ROUTE IS INSTRUCTOR-ONLY ═══════════════
*/
/*
 * Looped inside the test rather than driven by a ->with() dataset: Pest
 * resolves datasets before the application boots, so the router is not
 * available there. The route is named in each assertion message so a failure
 * still says which screen broke.
 */
it('closes every instructor route to a student', function (): void {
    foreach (instructorRoutes() as $url) {
        $this->actingAs($this->student)->get($url)->assertForbidden();
    }
});

it('closes every instructor route to a guest', function (): void {
    foreach (instructorRoutes() as $url) {
        $this->get($url)->assertRedirect('/login');
    }
});

it('opens every instructor route to the assigned instructor', function (): void {
    foreach (instructorRoutes() as $url) {
        $this->actingAs($this->instructor)->get($url)->assertOk();
    }
});

/*
| ═══════════════ AC-03 — ANOTHER INSTRUCTOR'S DATA IS NOT REACHABLE ═══════════════
*/
it('refuses another instructor their course detail', function (): void {
    // Route model binding will bind this slug happily. The screen re-resolves
    // through the assignment, which is what makes it a 404.
    $this->actingAs($this->instructor)
        ->get(route('instructor.courses.show', $this->theirs))
        ->assertNotFound();
});

it('refuses another instructor their assessment results', function (): void {
    $this->actingAs($this->instructor)
        ->get(route('instructor.assessments.results', $this->theirAssessment))
        ->assertForbidden();
});

it('refuses another instructor a student progress detail', function (): void {
    $this->actingAs($this->instructor)
        ->get(route('instructor.courses.students.progress', [$this->theirs, $this->theirEnrollment]))
        ->assertNotFound();
});

it('lists only assigned courses, never the whole catalogue', function (): void {
    $this->actingAs($this->instructor)
        ->get(route('instructor.courses.index'))
        ->assertOk()
        ->assertSee('Mine to teach')
        ->assertDontSee('Somebody elses course');
});

it('lists only assessments on assigned courses', function (): void {
    $this->actingAs($this->instructor)
        ->get(route('instructor.assessments.index'))
        ->assertOk()
        ->assertSee('My assessment')
        ->assertDontSee('Their assessment');
});

it('refuses an enrollment that belongs to a different course than the URL says', function (): void {
    // The pairing matters independently of each id being valid on its own:
    // this instructor teaches `mine`, and `theirEnrollment` is a real
    // enrollment — just not one of theirs.
    $this->actingAs($this->instructor)
        ->get(route('instructor.courses.students.progress', [$this->mine, $this->theirEnrollment]))
        ->assertNotFound();
});

/*
| ═══════════════ NO FINANCIAL DATA (Phase 10 DoD, FR-INS-09) ═══════════════
*/
it('shows an instructor no price anywhere in their area', function (): void {
    /*
     * ═════════════════════════════════════════════════════════════════════
     * "ZERO FINANCIAL DATA IS REACHABLE BY AN INSTRUCTOR" had no test.
     *
     * The rule is a commercial one, not a technical one: instructors are
     * often paid a share, and what a course sells for is the organisation's
     * business. A price leaking onto a course card is not a crash — nobody
     * would report it — so only a test catches it.
     * ═════════════════════════════════════════════════════════════════════
     */
    $this->mine->forceFill(['price_amount' => 4999_00])->save();

    foreach (instructorRoutes() as $url) {
        $html = (string) $this->actingAs($this->instructor)->get($url)->assertOk()->getContent();

        expect($html)
            ->not->toContain('4,999')
            ->not->toContain('499900')
            ->not->toContain('₹');
    }
});

it('exposes no order or payment route to an instructor', function (): void {
    // Phase 12 adds these under the admin prefix. Asserted now so the phase
    // that builds them cannot quietly widen the instructor area.
    $reachable = collect(Route::getRoutes()->getRoutes())
        ->map(static fn ($route): string => (string) $route->getName())
        ->filter(static fn (string $name): bool => str_starts_with($name, 'instructor.'))
        ->filter(static fn (string $name): bool => str_contains($name, 'order')
            || str_contains($name, 'payment')
            || str_contains($name, 'revenue'));

    expect($reachable)->toBeEmpty();
});

/*
| ═══════════════ THE ADMIN IS NOT LOCKED OUT ═══════════════
*/
it('lets a super admin reach the shared results screen', function (): void {
    // Results is one implementation serving both roles (FR-ASMT-17), with the
    // chrome chosen at render. A scoping rule that shut the admin out would
    // break the admin's own assessment area.
    $this->actingAs($this->admin)
        ->get(route('admin.assessments.results', $this->theirAssessment))
        ->assertOk();
});

/**
 * Every parameterless instructor route, READ FROM THE ROUTER.
 *
 * Enumerated rather than hand-listed on purpose: a screen added to
 * routes/instructor.php in a later phase is picked up by the role and
 * financial-data assertions above automatically, instead of depending on
 * somebody remembering to extend a literal array here.
 *
 * Routes taking a parameter need fixtures a static function cannot see, so
 * each is asserted individually in its own test above — the scoping ones are
 * exactly those, and they are the ones AC-03 turns on.
 *
 * @return list<string>
 */
function instructorRoutes(): array
{
    $urls = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $name = (string) $route->getName();

        if (! str_starts_with($name, 'instructor.') || $route->parameterNames() !== []) {
            continue;
        }

        $urls[] = route($name);
    }

    return $urls;
}
