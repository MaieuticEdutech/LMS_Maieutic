<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGateway;
use App\Models\Order;
use App\Support\Money;
use App\Support\Payment\GatewayEvent;
use App\Support\Payment\GatewayOrder;
use App\Support\Payment\GatewayPayment;
use Illuminate\Support\Str;

/**
 * The gateway double bound in every test environment (architecture.md
 * §11.1's own note: "Used in the entire test suite. No test ever calls
 * Razorpay.").
 *
 * A real gateway integration is untestable in CI by construction — it needs
 * network access to a third party, real credentials, and produces
 * non-deterministic identifiers. This class stands in for it completely:
 * every method a test needs is here, and {@see self::withEvent()} plus
 * {@see self::signPayload()} let a test construct exactly the webhook
 * delivery it wants to assert against, using the SAME signing algorithm
 * {@see self::verifyWebhookSignature()} checks, so a test proves the
 * checking code works rather than merely trusting a stub that always
 * returns true.
 */
final class FakeGateway implements PaymentGateway
{
    /** A fixed shared secret — deterministic, never a real credential. */
    public const string TEST_WEBHOOK_SECRET = 'fake-gateway-test-secret';

    public function createOrder(Order $order): GatewayOrder
    {
        return new GatewayOrder(
            id: 'order_fake_'.Str::random(14),
            amount: $order->amount_total,
            currency: $order->currency,
            status: 'created',
        );
    }

    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $expected = hash_hmac('sha256', $rawBody, self::TEST_WEBHOOK_SECRET);

        return hash_equals($expected, $signature);
    }

    public function parseEvent(array $payload): GatewayEvent
    {
        $type = (string) ($payload['event'] ?? '');
        $entity = $payload['payload']['payment']['entity'] ?? null;

        if (! is_array($entity)) {
            return new GatewayEvent(type: $type, payment: null);
        }

        return new GatewayEvent(
            type: $type,
            payment: new GatewayPayment(
                id: (string) $entity['id'],
                orderId: (string) $entity['order_id'],
                amount: (int) $entity['amount'],
                currency: (string) $entity['currency'],
                status: (string) $entity['status'],
                method: isset($entity['method']) ? (string) $entity['method'] : null,
                failureCode: isset($entity['error_code']) ? (string) $entity['error_code'] : null,
                failureReason: isset($entity['error_description']) ? (string) $entity['error_description'] : null,
            ),
        );
    }

    public function fetchPayment(string $gatewayPaymentId): GatewayPayment
    {
        // Reconciliation tests configure the response they want via
        // FakeGateway::fake() rather than this default, which exists only
        // so the class satisfies the interface without a test double
        // framework.
        return new GatewayPayment(
            id: $gatewayPaymentId,
            orderId: 'order_fake_unknown',
            amount: 0,
            currency: 'INR',
            status: 'created',
            method: null,
            failureCode: null,
            failureReason: null,
        );
    }

    public function fetchOrder(string $gatewayOrderId): GatewayOrder
    {
        return new GatewayOrder(
            id: $gatewayOrderId,
            amount: 0,
            currency: 'INR',
            status: 'created',
        );
    }

    /**
     * @return list<GatewayPayment>
     */
    public function fetchOrderPayments(string $gatewayOrderId): array
    {
        return self::$seededPayments[$gatewayOrderId] ?? [];
    }

    /**
     * @var array<string, list<GatewayPayment>>
     */
    private static array $seededPayments = [];

    /**
     * Configure what {@see self::fetchOrderPayments()} returns for a given
     * gateway order id — the reconciliation command's equivalent of a real
     * `payment.captured` webhook, for tests that exercise
     * {@see \App\Console\Commands\ReconcileOrders} without a webhook
     * delivery at all.
     */
    public static function seedOrderPayments(string $gatewayOrderId, GatewayPayment ...$payments): void
    {
        self::$seededPayments[$gatewayOrderId] = array_values($payments);
    }

    /**
     * Test isolation: called from a Pest `afterEach` where this gateway is
     * used, so one test's seeded payments never leak into the next.
     */
    public static function reset(): void
    {
        self::$seededPayments = [];
    }

    public function toGatewayAmount(Money $money): int
    {
        return $money->amount;
    }

    public function deriveEventId(GatewayEvent $event, string $rawBody): string
    {
        if ($event->payment !== null) {
            return "{$event->type}:{$event->payment->id}";
        }

        return "{$event->type}:".hash('sha256', $rawBody);
    }

    /**
     * Build a Razorpay-shaped `payment.captured` / `payment.failed` webhook
     * body for a given order, and sign it exactly as
     * {@see self::verifyWebhookSignature()} will check it.
     *
     * @param  array<string, mixed>  $overrides  Merged into the payment entity.
     * @return array{event_id: string, body: string, signature: string}
     */
    public static function signedWebhookPayload(
        string $eventType,
        Order $order,
        string $gatewayPaymentId,
        array $overrides = [],
    ): array {
        $eventId = 'evt_fake_'.Str::random(14);

        $entity = array_merge([
            'id' => $gatewayPaymentId,
            'order_id' => $order->gateway_order_id,
            'amount' => $order->amount_total,
            'currency' => $order->currency,
            'status' => $eventType === 'payment.captured' ? 'captured' : 'failed',
            'method' => 'card',
        ], $overrides);

        $body = json_encode([
            'event' => $eventType,
            'payload' => ['payment' => ['entity' => $entity]],
        ], JSON_THROW_ON_ERROR);

        return [
            'event_id' => $eventId,
            'body' => $body,
            'signature' => hash_hmac('sha256', $body, self::TEST_WEBHOOK_SECRET),
        ];
    }
}
