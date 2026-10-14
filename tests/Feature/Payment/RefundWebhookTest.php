<?php

declare(strict_types=1);

use App\Actions\Payment\ApplyRefund;
use App\Enums\EnrollmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Events\EnrollmentRevoked;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Enrollment\EnrollmentAccessService;
use App\Services\Payment\FakeGateway;
use App\Support\Payment\GatewayPayment;
use App\Support\Payment\GatewayRefund;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Phase 12 — a refund webhook revokes access (FR-ENR-09, FR-PAY-09, AC-15)
|--------------------------------------------------------------------------
|
| Real signed requests through the real endpoint and the real job (the suite
| runs the sync queue), so these prove the whole path: signature, dedupe,
| parsing, ApplyRefund, the enrollment, the audit trail and the notification.
*/

const REFUND_PRICE = 99900;

/**
 * A paid order whose purchase has been settled by a real captured webhook, so
 * there is a real enrollment to take away.
 *
 * @return array{0: Order, 1: Enrollment, 2: User, 3: Course}
 */
function settledPurchase(TestCase $test, string $paymentId = 'pay_refund_1'): array
{
    $course = Course::factory()->published()->pricedAt(REFUND_PRICE)->create();
    $order = Order::factory()->for($course)->pending()->create(['amount_total' => REFUND_PRICE]);

    ['body' => $body, 'signature' => $signature] = FakeGateway::signedWebhookPayload('payment.captured', $order, $paymentId);

    $test->call('POST', route('webhooks.razorpay'), [], [], [], [
        'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    $enrollment = Enrollment::query()->where('course_id', $course->getKey())->sole();

    // A guest buyer is created pending and cannot hold access until they
    // activate; the student in these tests has done so.
    $student = User::query()->findOrFail($enrollment->user_id);
    $student->forceFill(['status' => UserStatus::Active])->save();

    return [$order->refresh(), $enrollment, $student, $course];
}

function postRefund(TestCase $test, Order $order, string $paymentId, string $refundId, int $amount, int $total): void
{
    ['body' => $body, 'signature' => $signature] = FakeGateway::signedRefundPayload($order, $paymentId, $refundId, $amount, $total);

    $test->call('POST', route('webhooks.razorpay'), [], [], [], [
        'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();
}

it('a full refund moves the enrollment to refunded and the student loses access at once', function (): void {
    Event::fake([EnrollmentRevoked::class]);

    [$order, $enrollment, $student, $course] = settledPurchase($this);

    expect(app(EnrollmentAccessService::class)->grantsAccess($student, $course))->toBeTrue();

    postRefund($this, $order, 'pay_refund_1', 'rfnd_1', REFUND_PRICE, REFUND_PRICE);

    app(EnrollmentAccessService::class)->flush();

    $enrollment->refresh();
    $payment = Payment::query()->where('gateway_payment_id', 'pay_refund_1')->sole();

    expect($enrollment->status)->toBe(EnrollmentStatus::Refunded)
        ->and($enrollment->revoked_at)->not->toBeNull()
        ->and($enrollment->revoked_by)->toBeNull()
        ->and($enrollment->revoke_reason)->toContain('rfnd_1')
        ->and($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->refunded_amount)->toBe(REFUND_PRICE)
        ->and($order->refresh()->status)->toBe(OrderStatus::Refunded)
        ->and(app(EnrollmentAccessService::class)->grantsAccess($student, $course))->toBeFalse();

    Event::assertDispatched(
        EnrollmentRevoked::class,
        static fn (EnrollmentRevoked $e): bool => $e->enrollment->is($enrollment) && $e->wasAutomatic,
    );
});

it('audits the refund and the revocation with no user as the actor', function (): void {
    [$order] = settledPurchase($this);

    postRefund($this, $order, 'pay_refund_1', 'rfnd_1', REFUND_PRICE, REFUND_PRICE);

    $refund = AuditLog::query()->where('action', 'payment.refunded')->sole();
    $revoke = AuditLog::query()->where('action', 'enrollment.revoked')->sole();

    expect($refund->user_id)->toBeNull()
        ->and($refund->description)->toContain('Full refund')
        ->and($revoke->user_id)->toBeNull();
});

it('a partial refund records the amount and leaves access alone', function (): void {
    Event::fake([EnrollmentRevoked::class]);

    [$order, $enrollment, $student, $course] = settledPurchase($this);

    postRefund($this, $order, 'pay_refund_1', 'rfnd_part', 30000, 30000);

    app(EnrollmentAccessService::class)->flush();

    $payment = Payment::query()->where('gateway_payment_id', 'pay_refund_1')->sole();

    expect($payment->refunded_amount)->toBe(30000)
        ->and($payment->status)->toBe(PaymentStatus::Captured)
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($enrollment->refresh()->status)->toBe(EnrollmentStatus::Active)
        ->and(app(EnrollmentAccessService::class)->grantsAccess($student, $course))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'payment.refunded')->sole()->description)->toContain('Partial refund');

    Event::assertNotDispatched(EnrollmentRevoked::class);
});

it('a second refund on the same payment is not dropped as a duplicate delivery', function (): void {
    [$order, $enrollment] = settledPurchase($this);

    postRefund($this, $order, 'pay_refund_1', 'rfnd_a', 30000, 30000);
    postRefund($this, $order, 'pay_refund_1', 'rfnd_b', 69900, REFUND_PRICE);

    // Two refunds = two webhook events. Keyed on the payment id, the second
    // would have collided with the first on event_id and been ignored.
    expect(WebhookEvent::query()->where('event_type', 'refund.processed')->count())->toBe(2)
        ->and($enrollment->refresh()->status)->toBe(EnrollmentStatus::Refunded)
        ->and(Payment::query()->where('gateway_payment_id', 'pay_refund_1')->sole()->status)->toBe(PaymentStatus::Refunded);
});

it('a redelivered refund webhook changes nothing a second time', function (): void {
    [$order] = settledPurchase($this);

    ['body' => $body, 'signature' => $signature] = FakeGateway::signedRefundPayload($order, 'pay_refund_1', 'rfnd_1', REFUND_PRICE, REFUND_PRICE);

    foreach ([1, 2] as $_) {
        $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();
    }

    expect(WebhookEvent::query()->where('event_type', 'refund.processed')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'payment.refunded')->count())->toBe(1);
});

it('applying the same refund twice directly is also harmless', function (): void {
    [$order] = settledPurchase($this);

    $payment = new GatewayPayment('pay_refund_1', (string) $order->gateway_order_id, REFUND_PRICE, 'INR', 'refunded', 'card', null, null, REFUND_PRICE);
    $refund = new GatewayRefund('rfnd_1', 'pay_refund_1', REFUND_PRICE, 'processed');

    app(ApplyRefund::class)->handle($payment, $refund);
    app(ApplyRefund::class)->handle($payment, $refund);

    expect(AuditLog::query()->where('action', 'payment.refunded')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'enrollment.revoked')->count())->toBe(1);
});

it('never lowers the refunded total when events arrive out of order', function (): void {
    [$order] = settledPurchase($this);

    $refund = new GatewayRefund('rfnd_x', 'pay_refund_1', 1, 'processed');

    app(ApplyRefund::class)->handle(
        new GatewayPayment('pay_refund_1', (string) $order->gateway_order_id, REFUND_PRICE, 'INR', 'captured', 'card', null, null, 60000),
        $refund,
    );
    app(ApplyRefund::class)->handle(
        new GatewayPayment('pay_refund_1', (string) $order->gateway_order_id, REFUND_PRICE, 'INR', 'captured', 'card', null, null, 30000),
        $refund,
    );

    expect(Payment::query()->where('gateway_payment_id', 'pay_refund_1')->sole()->refunded_amount)->toBe(60000);
});

it('never records more than was captured', function (): void {
    [$order] = settledPurchase($this);

    app(ApplyRefund::class)->handle(
        new GatewayPayment('pay_refund_1', (string) $order->gateway_order_id, REFUND_PRICE, 'INR', 'refunded', 'card', null, null, REFUND_PRICE * 3),
        new GatewayRefund('rfnd_big', 'pay_refund_1', REFUND_PRICE * 3, 'processed'),
    );

    expect(Payment::query()->where('gateway_payment_id', 'pay_refund_1')->sole()->refunded_amount)->toBe(REFUND_PRICE);
});

it('fails loudly for a refund on a payment it has never recorded', function (): void {
    app(ApplyRefund::class)->handle(
        new GatewayPayment('pay_unknown', 'order_unknown', REFUND_PRICE, 'INR', 'refunded', 'card', null, null, REFUND_PRICE),
        new GatewayRefund('rfnd_u', 'pay_unknown', REFUND_PRICE, 'processed'),
    );
})->throws(DomainException::class, 'unknown payment');

it('does not revoke while another captured payment still stands behind the order', function (): void {
    [$order, $enrollment] = settledPurchase($this);

    // A second captured payment on the same order (e.g. a retried charge).
    $second = new Payment(['gateway' => 'razorpay', 'gateway_payment_id' => 'pay_refund_2', 'method' => 'card', 'currency' => 'INR', 'captured_at' => now()]);
    $second->order()->associate($order);
    $second->forceFill(['amount' => REFUND_PRICE, 'status' => PaymentStatus::Captured])->save();

    postRefund($this, $order, 'pay_refund_1', 'rfnd_1', REFUND_PRICE, REFUND_PRICE);

    expect(Payment::query()->where('gateway_payment_id', 'pay_refund_1')->sole()->status)->toBe(PaymentStatus::Refunded)
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($enrollment->refresh()->status)->toBe(EnrollmentStatus::Active);
});
