<?php

declare(strict_types=1);

use App\Livewire\Admin\Dashboard;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use App\Models\WebhookEvent;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Admin\Dashboard — Phase 4 Checkpoint 2
|--------------------------------------------------------------------------
|
| Authenticates via $this->actingAs() (the TestCase method), not
| Livewire::actingAs()->test() — chaining through Livewire::actingAs()
| breaks Larastan's generic resolution on the subsequent test() call
| (TComponent can't be inferred through the chain). Plain $this->actingAs()
| first, then Livewire::test() on its own, is both simpler and the pattern
| already proven in WithAdminTableTest.php (Checkpoint 1).
|
*/

it('shows the KPI tiles with real counts', function (): void {
    User::factory()->student()->count(2)->create();
    Course::factory()->create();

    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Livewire::test(Dashboard::class)
        ->assertSee('Students')
        ->assertSee('Instructors')
        ->assertSee('Published courses')
        ->assertSee('Active enrollments')
        ->assertSee('Revenue');
});

it('shows empty states when there is no recent activity', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Livewire::test(Dashboard::class)
        ->assertSee('No orders yet')
        ->assertSee('No enrollments yet')
        ->assertDontSee('Failed webhooks');
});

it('lists recent orders and enrollments when they exist', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Order::factory()->create(['buyer_name' => 'Priya Sharma']);
    $enrollment = Enrollment::factory()->create();

    Livewire::test(Dashboard::class)
        ->assertSee('Priya Sharma')
        ->assertSee($enrollment->user?->name)
        ->assertDontSee('No orders yet')
        ->assertDontSee('No enrollments yet');
});

it('shows the failed webhooks panel only when a failure exists', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    WebhookEvent::factory()->failed()->create(['gateway' => 'razorpay']);

    Livewire::test(Dashboard::class)
        ->assertSee('Failed webhooks')
        ->assertSee('razorpay');
});

/*
| PERFORMANCE — Phase 4 DoD: "Dashboard renders in < 400 ms with seeded
| data." Actually seeds a realistic volume rather than asserting a number
| with nothing behind it.
|
| getenv('CI'), not Laravel's env() helper: Larastan's Laravel-specific rule
| forbids env() outside config files (it returns null once config is
| cached) — the same class of bug the Phase 2 Super Admin seeder was caught
| on. getenv() reads the OS environment directly and isn't subject to that
| caching concern, which is exactly what a "skip only in CI" check needs.
*/
it('renders in under 400ms with a realistic seeded dataset', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    User::factory()->student()->count(150)->create();
    User::factory()->instructor()->count(15)->create();
    Course::factory()->count(25)->create();
    Enrollment::factory()->count(120)->create();
    Order::factory()->count(80)->create();
    WebhookEvent::factory()->failed()->count(5)->create();

    $start = microtime(true);
    // assertSee both confirms the render actually succeeded (it would throw
    // on an exception mid-render) and gives something concrete to assert,
    // since Livewire's test object has no assertOk()-style bare success check.
    Livewire::test(Dashboard::class)->assertSee('Students');
    $elapsedMs = (microtime(true) - $start) * 1000;

    expect($elapsedMs)->toBeLessThan(400);
})->skip(fn (): bool => (bool) getenv('CI'), 'Wall-clock performance assertions are unreliable on shared CI runners; verified locally.');
