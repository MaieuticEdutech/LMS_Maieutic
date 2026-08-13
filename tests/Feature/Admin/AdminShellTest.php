<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin shell — layout, navigation, breadcrumbs, role guard (Phase 4
| Checkpoint 1)
|--------------------------------------------------------------------------
|
| Iterates every REGISTERED admin.* route rather than hardcoding today's one
| route, so this guard stays correct as later Phase 4 checkpoints add more
| routes to routes/admin.php without anyone remembering to update this file
| (the same "assert against reality, not a fixed list" lesson from the
| Phase 3 build-ahead guards).
|
*/

/**
 * Only PARAMETER-FREE admin.* GET routes — `route($name)` with no arguments
 * throws for anything needing a `{student}` or similar (added from
 * Checkpoint 3 onward). Those routes get their own explicit tests, with a
 * real bound model, in their own test files (e.g. StudentDetailTest) — a
 * generic "wrong role gets 403" sweep can't usefully generalise to "on
 * which specific record", so it doesn't try to.
 *
 * @return list<string>
 */
function adminRouteNames(): array
{
    return array_values(collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteInstance $route): bool => str_starts_with((string) $route->getName(), 'admin.'))
        ->filter(fn (RouteInstance $route): bool => in_array('GET', $route->methods(), true))
        ->filter(fn (RouteInstance $route): bool => $route->parameterNames() === [])
        ->map(fn (RouteInstance $route): string => (string) $route->getName())
        ->all());
}

it('has at least one registered admin route to guard', function (): void {
    // A guard over an empty set passes vacuously and proves nothing — this
    // is the canary that would catch routes/admin.php being emptied out or
    // renamed without anyone noticing.
    expect(adminRouteNames())->not->toBeEmpty();
});

/*
| NOT dataset()-driven: Pest's dataset() closures are evaluated before the
| Laravel app is bootstrapped for a given test, so calling the Route facade
| from one throws "A facade root has not been set." Looping inside a single
| test body runs after TestCase::setUp(), where the facade is available —
| and still fails loudly (a failed assertion names the exact route) if a
| future route regresses, which is the property that matters here.
*/
it('redirects a guest away from every admin route', function (): void {
    foreach (adminRouteNames() as $routeName) {
        $this->get(route($routeName))->assertRedirect(route('login'));
    }
});

it('returns 403 for an authenticated instructor on every admin route', function (): void {
    $instructor = User::factory()->instructor()->create();

    foreach (adminRouteNames() as $routeName) {
        $this->actingAs($instructor)->get(route($routeName))->assertForbidden();
    }
});

it('returns 403 for an authenticated student on every admin route', function (): void {
    $student = User::factory()->student()->create();

    foreach (adminRouteNames() as $routeName) {
        $this->actingAs($student)->get(route($routeName))->assertForbidden();
    }
});

it('lets a super admin reach every admin route', function (): void {
    $admin = User::factory()->superAdmin()->create();

    foreach (adminRouteNames() as $routeName) {
        $this->actingAs($admin)->get(route($routeName))->assertOk();
    }
});

/*
| SHELL CONTENT — the layout itself, not just the gate.
*/
it('renders the sidebar, the user menu and a working logout for a super admin', function (): void {
    $admin = User::factory()->superAdmin()->create();

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertSee('Administration')
        ->assertSee($admin->name)
        ->assertSee($admin->role->label())
        ->assertSee(route('logout'), false);
});

it('shows breadcrumbs only when a page provides them', function (): void {
    expect(trim((string) $this->blade('<x-breadcrumbs :items="[]" />')))->toBe('');

    $rendered = (string) $this->blade('<x-breadcrumbs :items="$items" />', [
        'items' => [
            ['label' => 'Administration', 'url' => '/admin'],
            ['label' => 'Students', 'url' => null],
        ],
    ]);

    expect($rendered)->toContain('Administration')
        ->and($rendered)->toContain('Students')
        ->and($rendered)->toContain('aria-current="page"');
});
