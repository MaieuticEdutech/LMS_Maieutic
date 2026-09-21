<?php

declare(strict_types=1);

use App\Actions\Payment\SettleCapturedPayment;
use App\Enums\EnrollmentStatus;
use App\Enums\OrderStatus;
use App\Enums\UserStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PurchaseConfirmationNotification;
use App\Support\Payment\GatewayPayment;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Phase 12 — SettleCapturedPayment
|--------------------------------------------------------------------------
|
| The only caller of GrantEnrollment on the purchase path. Every test here
| is proving one of architecture.md §11.3's numbered rules, or the
| three-layer idempotency this class's own docblock claims.
|
*/

function gatewayPayment(Order $order, string $id, ?int $amount = null, ?string $currency = null): GatewayPayment
{
    return new GatewayPayment(
        id: $id,
        orderId: (string) $order->gateway_order_id,
        amount: $amount ?? $order->amount_total,
        currency: $currency ?? $order->currency,
        status: 'captured',
        method: 'card',
        failureCode: null,
        failureReason: null,
    );
}

it('pays the order, creates a purchase-created account, and grants exactly one enrollment', function (): void {
    Notification::fake();

    $course = Course::factory()->published()->pricedAt(149900)->create();
    $order = Order::factory()->for($course)->pending()->create([
        'amount_total' => 149900,
        'buyer_email' => 'new-buyer@example.com',
        'buyer_name' => 'New Buyer',
    ]);

    $payment = app(SettleCapturedPayment::class)->handle(gatewayPayment($order, 'pay_settle_new'));

    expect($payment->status->value)->toBe('captured')
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->paid_at)->not->toBeNull();

    // firstOrFail(), not first(): a genuinely missing buyer should fail this
    // test loudly, and — unlike first()'s nullable return — its non-nullable
    // return type is what lets the property accesses below type-check at
    // level 8 without a separate narrowing assertion.
    $buyer = User::query()->where('email', 'new-buyer@example.com')->firstOrFail();

    expect($buyer->status)->toBe(UserStatus::PendingActivation)
        // NULL, never a generated or emailed password (architecture.md
        // §7.2, AccountActivationNotification's own docblock).
        ->and($buyer->password)->toBeNull()
        ->and($order->user_id)->toBe($buyer->getKey());

    $enrollment = Enrollment::query()
        ->where('user_id', $buyer->getKey())
        ->where('course_id', $course->getKey())
        ->firstOrFail();

    expect($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->source->value)->toBe('purchase');

    Notification::assertSentTo($buyer, PurchaseConfirmationNotification::class);
});

it('resolves an existing account by buyer email instead of creating a second one', function (): void {
    $existing = User::factory()->student()->create(['email' => 'returning@example.com']);

    $course = Course::factory()->published()->pricedAt(199900)->create();
    $order = Order::factory()->for($course)->pending()->create([
        'amount_total' => 199900,
        'buyer_email' => 'returning@example.com',
    ]);

    app(SettleCapturedPayment::class)->handle(gatewayPayment($order, 'pay_settle_existing'));

    expect(User::query()->where('email', 'returning@example.com')->count())->toBe(1)
        ->and(
            Enrollment::query()
                ->where('user_id', $existing->getKey())
                ->where('course_id', $course->getKey())
                ->exists(),
        )->toBeTrue();
});

it('is idempotent for a repeated delivery of the same gateway payment', function (): void {
    Notification::fake();

    $course = Course::factory()->published()->pricedAt(99900)->create();
    $order = Order::factory()->for($course)->pending()->create([
        'amount_total' => 99900,
        'buyer_email' => 'idempotent-buyer@example.com',
    ]);

    $payment = gatewayPayment($order, 'pay_settle_repeat');

    $first = app(SettleCapturedPayment::class)->handle($payment);
    $second = app(SettleCapturedPayment::class)->handle($payment);

    expect($second->is($first))->toBeTrue()
        ->and(Payment::query()->where('gateway_payment_id', 'pay_settle_repeat')->count())->toBe(1)
        ->and(Enrollment::query()->where('course_id', $course->getKey())->count())->toBe(1);

    $buyer = User::query()->where('email', 'idempotent-buyer@example.com')->firstOrFail();

    // Layer 1's own point: the early return happens before anything is
    // dispatched a second time.
    Notification::assertSentToTimes($buyer, PurchaseConfirmationNotification::class, 1);
});

it('rejects a payment whose amount does not match the order, and grants nothing', function (): void {
    $course = Course::factory()->published()->pricedAt(99900)->create();
    $order = Order::factory()->for($course)->pending()->create(['amount_total' => 99900]);

    $mismatched = gatewayPayment($order, 'pay_mismatch', amount: 50000);

    expect(fn () => app(SettleCapturedPayment::class)->handle($mismatched))
        ->toThrow(DomainException::class);

    expect($order->refresh()->status)->toBe(OrderStatus::Pending)
        ->and(Enrollment::query()->where('course_id', $course->getKey())->count())->toBe(0)
        ->and(Payment::query()->where('gateway_payment_id', 'pay_mismatch')->exists())->toBeFalse();
});

it('rejects a payment whose currency does not match the order', function (): void {
    $course = Course::factory()->published()->pricedAt(99900)->create();
    $order = Order::factory()->for($course)->pending()->create(['amount_total' => 99900, 'currency' => 'INR']);

    $mismatched = gatewayPayment($order, 'pay_currency_mismatch', currency: 'USD');

    expect(fn () => app(SettleCapturedPayment::class)->handle($mismatched))
        ->toThrow(DomainException::class);

    expect(Enrollment::query()->where('course_id', $course->getKey())->count())->toBe(0);
});

it('rejects a payment for a gateway order this application never created', function (): void {
    $order = Order::factory()->pending()->create();

    $orphan = new GatewayPayment(
        id: 'pay_orphan',
        orderId: 'order_this_app_never_made',
        amount: $order->amount_total,
        currency: $order->currency,
        status: 'captured',
        method: 'card',
        failureCode: null,
        failureReason: null,
    );

    expect(fn () => app(SettleCapturedPayment::class)->handle($orphan))
        ->toThrow(DomainException::class);
});
