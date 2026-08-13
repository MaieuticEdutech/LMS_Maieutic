<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\EnrollmentSource;
use App\Models\Course;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Phase 6 UI — the routes, through the real middleware stack
|--------------------------------------------------------------------------
|
| The Livewire tests exercise the components; these exercise the way a browser
| actually reaches them. Both are needed: a component can be perfectly
| authorised and still be mounted on a route with the wrong middleware, and
| Livewire::test() bypasses the stack entirely.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
});

it('renders the enrolments list for an administrator', function (): void {
    $student = User::factory()->create(['name' => 'Asha Rao']);
    $course = Course::factory()->create(['title' => 'Advanced Widgetry']);

    app(GrantEnrollment::class)->handle(
        student: $student,
        course: $course,
        source: EnrollmentSource::AdminGrant,
        actor: $this->admin,
    );

    $this->actingAs($this->admin)
        ->get(route('admin.enrollments.index'))
        ->assertOk()
        ->assertSee('Enrolments')
        ->assertSee('Asha Rao')
        ->assertSee('Advanced Widgetry');
});

it('renders the grant form for an administrator', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.enrollments.create'))
        ->assertOk()
        ->assertSee('Grant access');
});

it('blocks a student from both screens', function (string $route): void {
    $this->actingAs(User::factory()->create())
        ->get(route($route))
        ->assertForbidden();
})->with(['admin.enrollments.index', 'admin.enrollments.create']);

it('blocks an instructor from both screens', function (string $route): void {
    $this->actingAs(User::factory()->instructor()->create())
        ->get(route($route))
        ->assertForbidden();
})->with(['admin.enrollments.index', 'admin.enrollments.create']);

it('sends a guest to the login page', function (string $route): void {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['admin.enrollments.index', 'admin.enrollments.create']);

it('links enrolments in the admin navigation', function (): void {
    /*
     * The shell renders nav items only when the route exists (Route::has), so
     * registering these routes is what makes the link appear — no edit to the
     * layout, which is Track B's file. This asserts that seam still holds.
     */
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.enrollments.index'));
});
