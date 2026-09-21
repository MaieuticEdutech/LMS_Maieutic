<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Livewire\Checkout\Start;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

it('creates a pending order and opens checkout for a guest buyer', function (): void {
    $course = Course::factory()->published()->pricedAt(99900)->create();

    Livewire::test(Start::class, ['course' => $course])
        ->set('name', 'Guest Buyer')
        ->set('email', 'guest@example.com')
        ->set('phone', '9876543210')
        ->call('submit')
        ->assertDispatched('checkout:open');

    $order = Order::query()->where('course_id', $course->getKey())->firstOrFail();

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->buyer_email)->toBe('guest@example.com')
        // Guest checkout does not require an account — user_id stays null
        // until SettleCapturedPayment resolves a buyer from a VERIFIED
        // payment (architecture.md §11.3 rule 1/2), never at checkout time.
        ->and($order->user_id)->toBeNull();
});

it('prefills a signed-in student\'s own name and email', function (): void {
    $student = User::factory()->student()->create(['name' => 'Signed In Student', 'email' => 'student@example.com']);
    $course = Course::factory()->published()->pricedAt(99900)->create();

    // $this->actingAs(), not Livewire::actingAs()->test() — the chained form
    // is not statically resolvable to Livewire::test()'s generic template at
    // level 8; every other Livewire test in this codebase authenticates the
    // same way (AuditLogTableTest et al.).
    $this->actingAs($student);

    Livewire::test(Start::class, ['course' => $course])
        ->assertSet('name', 'Signed In Student')
        ->assertSet('email', 'student@example.com');
});

it('sends an already-enrolled student straight to the course instead of checkout', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->pricedAt(99900)->create();

    // Default factory state is already status=Active (EnrollmentFactory).
    Enrollment::factory()->for($student, 'user')->for($course)->create();

    $this->actingAs($student);

    Livewire::test(Start::class, ['course' => $course])
        ->assertRedirect(route('student.courses.play', ['course' => $course]));

    expect(Order::query()->where('course_id', $course->getKey())->exists())->toBeFalse();
});

it('refuses a guest checkout attempt on an unpublished course', function (): void {
    // CoursePolicy::view() denies a guest on a draft course before this
    // component's own status check ever runs — same 403, same reason
    // Catalogue\Show gets for the identical case.
    $course = Course::factory()->create();

    Livewire::test(Start::class, ['course' => $course])
        ->assertStatus(403);
});

// No "free course" test here either: courses_price_positive_check makes a
// zero-price course impossible to construct at all — see
// InitiateCheckoutTest's identical note.
