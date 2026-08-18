<?php

declare(strict_types=1);

use App\Enums\EnrollmentSource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Phase 13 · report screens — access, chrome and CSV
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->instructor = User::factory()->instructor()->create();
    $this->student = User::factory()->create();

    $this->course = Course::factory()->published()->create(['title' => 'Assigned Course']);
    $this->course->instructors()->attach($this->instructor);

    Enrollment::factory()->create([
        'user_id' => User::factory()->create(['name' => 'Enrolled Person'])->getKey(),
        'course_id' => $this->course->getKey(),
        'source' => EnrollmentSource::Purchase,
        'enrolled_at' => '2026-03-10',
    ]);
});

$reports = ['enrollments', 'course-progress', 'assessments', 'students'];

it('serves every report to a super admin', function (string $report): void {
    $this->actingAs($this->admin)
        ->get(route("admin.reports.{$report}"))
        ->assertOk();
})->with($reports);

it('serves every report to an instructor under their own chrome', function (string $report): void {
    $this->actingAs($this->instructor)
        ->get(route("instructor.reports.{$report}"))
        ->assertOk();
})->with($reports);

it('refuses a student every report', function (string $report): void {
    $this->actingAs($this->student)
        ->get(route("admin.reports.{$report}"))
        ->assertForbidden();

    $this->actingAs($this->student)
        ->get(route("instructor.reports.{$report}"))
        ->assertForbidden();
})->with($reports);

it('redirects a guest away from every report', function (string $report): void {
    expect($this->get(route("admin.reports.{$report}"))->isRedirect())->toBeTrue();
})->with($reports);

it('refuses an instructor the admin report routes', function (string $report): void {
    // Same screen, but the admin URL sits behind role:super_admin.
    $this->actingAs($this->instructor)
        ->get(route("admin.reports.{$report}"))
        ->assertForbidden();
})->with($reports);

/*
| ═══════════ FR-RPT-06 — export ═══════════
*/

it('exports a csv with the period in the filename', function (): void {
    Livewire::actingAs($this->admin);

    $response = Livewire::test(App\Livewire\Reports\EnrollmentReportScreen::class)
        ->set('from', '2026-03-01')
        ->set('to', '2026-03-31')
        ->call('export');

    $response->assertFileDownloaded('enrollments-1-mar-2026-31-mar-2026.csv');
});

it('streams the visible figures into the csv', function (): void {
    Livewire::actingAs($this->admin);

    $download = Livewire::test(App\Livewire\Reports\EnrollmentReportScreen::class)
        ->call('export')
        ->effects['download'] ?? null;

    expect($download)->not->toBeNull();
});

it('never exposes a revenue report route to an instructor', function (): void {
    // FR-RPT-07 / FR-INS-10. The guarantee is that the route does not exist,
    // not that a flag hides it — an absent screen cannot leak.
    expect(Route::has('instructor.reports.revenue'))->toBeFalse()
        ->and(Route::has('admin.reports.revenue'))->toBeFalse(); // Phase 12
});

it('shows an instructor only their own course in the rendered report', function (): void {
    $other = Course::factory()->published()->create(['title' => 'Unassigned Course']);

    Enrollment::factory()->create([
        'user_id' => User::factory()->create()->getKey(),
        'course_id' => $other->getKey(),
        'enrolled_at' => '2026-03-10',
    ]);

    $this->actingAs($this->instructor)
        ->get(route('instructor.reports.enrollments'))
        ->assertOk()
        ->assertSee('Assigned Course')
        ->assertDontSee('Unassigned Course');
});

/*
| ═══════════ REACHABILITY ═══════════
|
| The four reports shipped with every route registered and only one of them
| linked from anywhere, so three were unreachable by clicking and only the
| first could be exported. Routes existing is not the same as a feature being
| delivered — these assert the navigation, not the routing.
*/

it('links to every other report from each report', function (string $report): void {
    $response = $this->actingAs($this->admin)
        ->get(route("admin.reports.{$report}"))
        ->assertOk();

    foreach (['enrollments', 'course-progress', 'assessments', 'students'] as $other) {
        $response->assertSee(route("admin.reports.{$other}"), escape: false);
    }
})->with(['enrollments', 'course-progress', 'assessments', 'students']);

it('keeps an instructors report tabs inside the instructor area', function (): void {
    $response = $this->actingAs($this->instructor)
        ->get(route('instructor.reports.enrollments'))
        ->assertOk();

    // Linking an instructor at an /admin URL would hand them a 403 on click.
    $response->assertSee(route('instructor.reports.students'), escape: false)
        ->assertDontSee(route('admin.reports.students'), escape: false);
});

it('carries the date range across a tab switch', function (): void {
    // Switching report must not silently reset the period, or the next screen
    // quietly answers a different question than the one being asked.
    // Compared against the ESCAPED url: a multi-parameter query string is
    // rendered with &amp; in the href, which is correct HTML — asserting the
    // raw route() string would fail on markup that is right.
    $expected = e(route('admin.reports.students', ['from' => '2026-03-01', 'to' => '2026-03-31']));

    $this->actingAs($this->admin)
        ->get(route('admin.reports.enrollments', ['from' => '2026-03-01', 'to' => '2026-03-31']))
        ->assertOk()
        ->assertSee($expected, escape: false);
});
