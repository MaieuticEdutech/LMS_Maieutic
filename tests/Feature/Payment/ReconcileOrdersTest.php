<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Services\Payment\FakeGateway;
use App\Support\Payment\GatewayPayment;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Phase 12 — lms:orders:reconcile
|--------------------------------------------------------------------------
|
| architecture.md §11.3 rule 8: "Missed webhooks are self-healing." Every
| test here settles or fails an order through the gateway lookup alone, with
| no webhook delivery involved at all — proving the self-healing path is
| independent of the primary one, not merely untested.
|
| Artisan::call rather than $this->artisan()->assertExitCode() throughout —
| same reason as ProgressRebuildTest / CancelAbandonedOrdersTest: a plain int
| return, not the PendingCommand fluent API PHPStan cannot resolve at level 8.
|
*/

afterEach(function (): void {
    FakeGateway::reset();
});

it('settles a stale pending order once the gateway reports the payment captured', function (): void {
    $course = Course::factory()->published()->pricedAt(99900)->create();
    $order = Order::factory()->for($course)->pending()->create([
        'amount_total' => 99900,
        'placed_at' => now()->subMinutes(30),
    ]);

    FakeGateway::seedOrderPayments(
        (string) $order->gateway_order_id,
        new GatewayPayment(
            id: 'pay_reconciled',
            orderId: (string) $order->gateway_order_id,
            amount: 99900,
            currency: 'INR',
            status: 'captured',
            method: 'upi',
            failureCode: null,
            failureReason: null,
        ),
    );

    expect(Artisan::call('lms:orders:reconcile'))->toBe(0);

    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(Enrollment::query()->where('course_id', $course->getKey())->count())->toBe(1);
});

it('fails a stale pending order once the gateway reports the payment failed', function (): void {
    $course = Course::factory()->published()->pricedAt(99900)->create();
    $order = Order::factory()->for($course)->pending()->create([
        'amount_total' => 99900,
        'placed_at' => now()->subMinutes(30),
    ]);

    FakeGateway::seedOrderPayments(
        (string) $order->gateway_order_id,
        new GatewayPayment(
            id: 'pay_reconciled_failed',
            orderId: (string) $order->gateway_order_id,
            amount: 99900,
            currency: 'INR',
            status: 'failed',
            method: 'card',
            failureCode: 'GATEWAY_ERROR',
            failureReason: 'Bank declined.',
        ),
    );

    expect(Artisan::call('lms:orders:reconcile'))->toBe(0);

    expect($order->refresh()->status)->toBe(OrderStatus::Failed);
});

it('leaves a genuinely still-pending order untouched', function (): void {
    $order = Order::factory()->pending()->create(['placed_at' => now()->subMinutes(30)]);

    expect(Artisan::call('lms:orders:reconcile'))->toBe(0);

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

it('does not consider a recently-placed order stale', function (): void {
    $order = Order::factory()->pending()->create(['placed_at' => now()->subMinutes(2)]);

    FakeGateway::seedOrderPayments(
        (string) $order->gateway_order_id,
        new GatewayPayment(
            id: 'pay_too_soon',
            orderId: (string) $order->gateway_order_id,
            amount: $order->amount_total,
            currency: $order->currency,
            status: 'captured',
            method: 'card',
            failureCode: null,
            failureReason: null,
        ),
    );

    expect(Artisan::call('lms:orders:reconcile'))->toBe(0);

    // Never even looked up — the whole point of the --minutes window.
    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

it('is idempotent alongside a settlement that already happened', function (): void {
    $course = Course::factory()->published()->pricedAt(99900)->create();
    $order = Order::factory()->for($course)->paid()->create([
        'amount_total' => 99900,
        'placed_at' => now()->subMinutes(30),
    ]);

    // A paid order is never selected in the first place (status filter), so
    // reconciliation cannot touch it regardless of what the gateway says.
    expect(Artisan::call('lms:orders:reconcile'))->toBe(0);

    expect($order->refresh()->status)->toBe(OrderStatus::Paid);
});
