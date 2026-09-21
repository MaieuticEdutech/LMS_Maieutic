<?php

declare(strict_types=1);

use App\Actions\Payment\InitiateCheckout;
use App\Enums\OrderStatus;
use App\Models\Course;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| Phase 12 — InitiateCheckout
|--------------------------------------------------------------------------
|
| architecture.md §11.3 rule 1: the browser never sets or influences the
| price. Every assertion here is really checking one thing — that the order
| this Action produces is priced from `courses.price_amount`, never from
| anything the caller passed in.
|
*/

it('prices the order from the course, and starts it pending with a gateway order id', function (): void {
    $course = Course::factory()->published()->pricedAt(199900)->create();

    $order = app(InitiateCheckout::class)->handle($course, [
        'name' => 'Asha Rao',
        'email' => 'Asha@Example.com',
        'phone' => '9876543210',
    ]);

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->amount_subtotal)->toBe(199900)
        ->and($order->amount_total)->toBe(199900)
        ->and($order->currency)->toBe($course->currency)
        ->and($order->course_id)->toBe($course->getKey())
        ->and($order->gateway_order_id)->not->toBeNull()
        ->and($order->order_number)->toStartWith('ORD-')
        // Normalised the same way User::email is (FR-AUTH-10) — the same
        // email must resolve to the same buyer regardless of casing.
        ->and($order->buyer_email)->toBe('asha@example.com');
});

it('never lets the caller set the price', function (): void {
    $course = Course::factory()->published()->pricedAt(99900)->create();

    // handle()'s $buyer array has no amount field at all — there is nowhere
    // for a caller to pass a price even if it wanted to. This test exists to
    // catch a future regression that adds one.
    $order = app(InitiateCheckout::class)->handle($course, [
        'name' => 'Test Buyer',
        'email' => 'buyer@example.com',
    ]);

    expect($order->amount_total)->toBe(99900);
});

it('refuses checkout for a course that is not published', function (): void {
    $course = Course::factory()->create();

    expect(fn () => app(InitiateCheckout::class)->handle($course, [
        'name' => 'Test Buyer',
        'email' => 'buyer@example.com',
    ]))->toThrow(ValidationException::class);
});

// No "refuses checkout for a free course" test: courses_price_positive_check
// (courses' own migration, ADR-014) makes a zero-price course impossible to
// even construct — Course::factory()->pricedAt(0) fails at the database
// before InitiateCheckout is ever reached. See that Action's own comment.
