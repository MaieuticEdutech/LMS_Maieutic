<?php

declare(strict_types=1);

use App\Enums\CourseStatus;
use App\Enums\OrderStatus;
use App\Enums\WebhookStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Admin\DashboardQueryService;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| DashboardQueryService — Phase 4 Checkpoint 2
|--------------------------------------------------------------------------
|
| phases.md: "Admin dashboard query service (counts and recent lists; zero
| N+1)". The N+1 tests below prove that directly — query count must not
| scale with row count — not just assert the numbers come back right.
|
*/

it('counts students, instructors, published courses and active enrollments', function (): void {
    // Deliberately careful about factory cascades here: Enrollment's default
    // state creates its own nested user AND a nested paid order (which
    // itself creates its own nested buyer) unless told otherwise — left
    // alone, `Enrollment::factory()->count(4)->create()` would silently add
    // 8 phantom students on top of the 3 explicit ones, and this test would
    // have been asserting against however many happened to land, not a real
    // number. adminGranted() skips the order cascade (order_id stays null);
    // reusing one explicit student and cycling through throwaway courses
    // (UNIQUE(user_id, course_id) forbids reusing the same pair) skips the
    // rest.
    $student = User::factory()->student()->create();
    User::factory()->student()->count(2)->create();
    User::factory()->instructor()->count(2)->create();
    Course::factory()->create(['status' => CourseStatus::Published]);
    Course::factory()->create(['status' => CourseStatus::Draft]);

    $enrollmentCourses = Course::factory()->count(5)->create(); // defaults to Draft

    foreach ($enrollmentCourses->take(4) as $course) {
        Enrollment::factory()->adminGranted()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }

    Enrollment::factory()->adminGranted()->suspended()->create([
        'user_id' => $student->id,
        'course_id' => $enrollmentCourses->last()?->id,
    ]);

    $dashboard = app(DashboardQueryService::class);

    expect($dashboard->studentCount())->toBe(3)
        ->and($dashboard->instructorCount())->toBe(2)
        ->and($dashboard->publishedCourseCount())->toBe(1)
        ->and($dashboard->activeEnrollmentCount())->toBe(4);
});

it('sums only paid orders into total revenue', function (): void {
    Order::factory()->paid()->create(['amount_total' => 150000]);
    Order::factory()->paid()->create(['amount_total' => 250000]);
    Order::factory()->create(['amount_total' => 99900]); // status: created, not paid
    Order::factory()->failed()->create(['amount_total' => 50000]);

    $dashboard = app(DashboardQueryService::class);
    $revenue = $dashboard->totalRevenue();

    expect($revenue->amount)->toBe(400000)
        ->and($revenue->currency)->toBe('INR');
});

it('returns the most recent orders, newest first, up to the limit', function (): void {
    $older = Order::factory()->create(['created_at' => now()->subDays(3)]);
    $newer = Order::factory()->create(['created_at' => now()->subDay()]);
    $newest = Order::factory()->create(['created_at' => now()]);

    $dashboard = app(DashboardQueryService::class);
    $recent = $dashboard->recentOrders(2);

    expect($recent)->toHaveCount(2)
        ->and($recent->pluck('id')->all())->toBe([$newest->id, $newer->id]);
});

it('does not N+1 when listing recent orders with their course titles', function (): void {
    Order::factory()->count(10)->create();

    DB::enableQueryLog();
    app(DashboardQueryService::class)->recentOrders(10)->each(fn (Order $order) => $order->course?->title);
    $withTen = count(DB::getQueryLog());
    DB::flushQueryLog();

    // Flushed again right before measuring, not just after: without this,
    // the 20 factory-cascaded inserts below (each order also creates its own
    // course and buyer) land IN the query log too, and $withThirty measures
    // "20 orders' worth of setup queries" instead of "one read." Missed this
    // the first time and the test failed for a reason that had nothing to
    // do with the service actually N+1-ing.
    Order::factory()->count(20)->create();
    DB::flushQueryLog();

    app(DashboardQueryService::class)->recentOrders(30)->each(fn (Order $order) => $order->course?->title);
    $withThirty = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($withTen)->toBe($withThirty);
});

it('does not N+1 when listing recent enrollments with student and course names', function (): void {
    Enrollment::factory()->count(10)->create();

    DB::enableQueryLog();
    app(DashboardQueryService::class)->recentEnrollments(10)
        ->each(fn (Enrollment $enrollment) => [$enrollment->user?->name, $enrollment->course?->title]);
    $withTen = count(DB::getQueryLog());
    DB::flushQueryLog();

    // Same fix as the orders test above — flush again after the second
    // factory batch, before measuring, not just before it.
    Enrollment::factory()->count(20)->create();
    DB::flushQueryLog();

    app(DashboardQueryService::class)->recentEnrollments(30)
        ->each(fn (Enrollment $enrollment) => [$enrollment->user?->name, $enrollment->course?->title]);
    $withThirty = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($withTen)->toBe($withThirty);
});

it('returns only failed webhooks, most recent first', function (): void {
    WebhookEvent::factory()->create(['status' => WebhookStatus::Processed]);
    $olderFailure = WebhookEvent::factory()->failed()->create(['created_at' => now()->subHour()]);
    $newerFailure = WebhookEvent::factory()->failed()->create(['created_at' => now()]);

    $dashboard = app(DashboardQueryService::class);
    $failed = $dashboard->recentFailedWebhooks(5);

    expect($failed)->toHaveCount(2)
        ->and($failed->pluck('id')->all())->toBe([$newerFailure->id, $olderFailure->id])
        ->and($failed->every(fn (WebhookEvent $event) => $event->status === WebhookStatus::Failed))->toBeTrue();
});

it('excludes non-paid orders even when many exist, without scanning them all into revenue', function (): void {
    Order::factory()->count(15)->create(['status' => OrderStatus::Created]);
    Order::factory()->paid()->create(['amount_total' => 100000]);

    expect(app(DashboardQueryService::class)->totalRevenue()->amount)->toBe(100000);
});
