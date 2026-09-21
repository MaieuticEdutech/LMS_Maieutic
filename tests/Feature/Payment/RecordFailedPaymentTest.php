<?php

declare(strict_types=1);

use App\Actions\Payment\RecordFailedPayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\Order;
use App\Notifications\PaymentFailedNotification;
use App\Support\Payment\GatewayPayment;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

it('records the failure and marks a pending order failed', function (): void {
    Notification::fake();

    $course = Course::factory()->published()->pricedAt(99900)->create();
    $order = Order::factory()->for($course)->pending()->create([
        'amount_total' => 99900,
        'buyer_email' => 'declined@example.com',
    ]);

    $gatewayPayment = new GatewayPayment(
        id: 'pay_declined',
        orderId: (string) $order->gateway_order_id,
        amount: 99900,
        currency: 'INR',
        status: 'failed',
        method: 'card',
        failureCode: 'BAD_REQUEST_ERROR',
        failureReason: 'The card was declined.',
    );

    // handle() is genuinely ?Payment (null only when the order is unknown,
    // not this test's case) — narrowed the same way SubmitCourseReview /
    // IssueCertificate narrow a schema-guaranteed relation, so the test
    // fails loudly rather than silently passing a separate not-null check.
    $payment = app(RecordFailedPayment::class)->handle($gatewayPayment)
        ?? throw new RuntimeException('Expected a payment to be recorded.');

    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->failure_reason)->toBe('The card was declined.')
        ->and($order->refresh()->status)->toBe(OrderStatus::Failed)
        ->and($order->failed_reason)->toBe('The card was declined.');

    Notification::assertSentOnDemand(
        PaymentFailedNotification::class,
        fn (PaymentFailedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'declined@example.com',
    );
});

it('never turns an already-paid order into a failed one on a late failure event', function (): void {
    $course = Course::factory()->published()->pricedAt(99900)->create();
    $order = Order::factory()->for($course)->paid()->create(['amount_total' => 99900]);

    $gatewayPayment = new GatewayPayment(
        id: 'pay_late_failure',
        orderId: (string) $order->gateway_order_id,
        amount: 99900,
        currency: 'INR',
        status: 'failed',
        method: 'card',
        failureCode: null,
        failureReason: null,
    );

    app(RecordFailedPayment::class)->handle($gatewayPayment);

    expect($order->refresh()->status)->toBe(OrderStatus::Paid);
});

it('is idempotent for a repeated failure delivery', function (): void {
    $course = Course::factory()->published()->pricedAt(99900)->create();
    $order = Order::factory()->for($course)->pending()->create(['amount_total' => 99900]);

    $gatewayPayment = new GatewayPayment(
        id: 'pay_failed_repeat',
        orderId: (string) $order->gateway_order_id,
        amount: 99900,
        currency: 'INR',
        status: 'failed',
        method: 'card',
        failureCode: null,
        failureReason: null,
    );

    $first = app(RecordFailedPayment::class)->handle($gatewayPayment);
    $second = app(RecordFailedPayment::class)->handle($gatewayPayment);

    expect($second?->is($first))->toBeTrue()
        ->and(App\Models\Payment::query()->where('gateway_payment_id', 'pay_failed_repeat')->count())->toBe(1);
});
