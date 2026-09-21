<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGateway;
use App\Models\Order;
use App\Support\Money;
use App\Support\Payment\GatewayEvent;
use App\Support\Payment\GatewayOrder;
use App\Support\Payment\GatewayPayment;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use Razorpay\Api\Order as RazorpayOrder;
use Razorpay\Api\Payment as RazorpayPayment;
use Razorpay\Api\Utility as RazorpayUtility;
use RuntimeException;

/**
 * The real gateway (architecture.md §11.1). All Razorpay SDK usage,
 * response shapes and the signature algorithm live here and nowhere else —
 * every other class in this application depends on
 * {@see PaymentGateway} only.
 *
 * NEVER BOUND IN TESTING (see {@see \App\Providers\PaymentServiceProvider}).
 * No test in this suite constructs this class or reaches Razorpay's network.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * ONE DELIBERATE DEPARTURE FROM THE `webhook_events` MIGRATION'S COMMENT.
 *
 * That migration says `event_id` is "the gateway's own event identifier, not
 * ours to generate." Razorpay's actual webhook delivery body carries no such
 * field — verified against this SDK's own fixture
 * (vendor/razorpay/razorpay/documents/paymentVerfication.md): the payload is
 * `{entity, account_id, event, contains, payload, created_at}`, with no
 * top-level id. What Razorpay DOES provide is the payment's own id inside
 * the payload, and the event type string.
 *
 * {@see self::deriveEventId()} composes `"{event}:{payment.id}"` from those
 * two gateway-owned values rather than inventing a random one. The property
 * `event_id` exists to protect — the SAME delivery, retried, collides on
 * `UNIQUE(event_id)` — holds under this composition exactly as it would
 * under a single field, because Razorpay never reuses a payment id for a
 * different event of the same type.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class RazorpayGateway implements PaymentGateway
{
    /**
     * Only ever used to set `Api::$key` / `Api::$secret` — the two static
     * properties every entity class below reads internally when it makes a
     * request. The instance itself is not kept: {@see Api::__get()} is a
     * magic accessor (`new (ucwords($name))()`) that PHPStan cannot see
     * through, so every entity below is constructed directly instead of via
     * `$api->order`/`$api->payment`/`$api->utility` — same objects, same
     * statics, but a type the analyser can actually check.
     */
    public function __construct()
    {
        new Api(
            config()->string('lms.payments.razorpay.key_id'),
            config()->string('lms.payments.razorpay.key_secret'),
        );
    }

    public function createOrder(Order $order): GatewayOrder
    {
        $response = (new RazorpayOrder)->create([
            'amount' => $order->amount_total,
            'currency' => $order->currency,
            'receipt' => $order->order_number,
        ])->toArray();

        return new GatewayOrder(
            id: (string) $response['id'],
            amount: (int) $response['amount'],
            currency: (string) $response['currency'],
            status: (string) $response['status'],
        );
    }

    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = config()->string('lms.payments.razorpay.webhook_secret');

        try {
            (new RazorpayUtility)->verifyWebhookSignature($rawBody, $signature, $secret);

            return true;
        } catch (SignatureVerificationError) {
            return false;
        }
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
            payment: $this->paymentFromArray($entity),
        );
    }

    public function fetchPayment(string $gatewayPaymentId): GatewayPayment
    {
        $response = (new RazorpayPayment)->fetch($gatewayPaymentId)->toArray();

        return $this->paymentFromArray($response);
    }

    public function fetchOrder(string $gatewayOrderId): GatewayOrder
    {
        $response = (new RazorpayOrder)->fetch($gatewayOrderId)->toArray();

        return new GatewayOrder(
            id: (string) $response['id'],
            amount: (int) $response['amount'],
            currency: (string) $response['currency'],
            status: (string) $response['status'],
        );
    }

    public function fetchOrderPayments(string $gatewayOrderId): array
    {
        $response = (new RazorpayOrder)->fetch($gatewayOrderId)->payments()->toArray();

        $items = $response['items'] ?? [];

        return array_values(array_map(
            fn (array $attributes): GatewayPayment => $this->paymentFromArray($attributes),
            $items,
        ));
    }

    public function toGatewayAmount(Money $money): int
    {
        // Pass-through: Razorpay already bills in paise for INR, the only
        // currency V1 supports (Money's own class docblock). A future
        // currency whose minor unit does not match Razorpay's expectation
        // would need this method to actually convert.
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
     * @param  array<string, mixed>  $attributes
     */
    private function paymentFromArray(array $attributes): GatewayPayment
    {
        if (! isset($attributes['id'], $attributes['order_id'])) {
            throw new RuntimeException('Razorpay payment payload is missing id or order_id.');
        }

        return new GatewayPayment(
            id: (string) $attributes['id'],
            orderId: (string) $attributes['order_id'],
            amount: (int) $attributes['amount'],
            currency: (string) $attributes['currency'],
            status: (string) $attributes['status'],
            method: isset($attributes['method']) ? (string) $attributes['method'] : null,
            failureCode: isset($attributes['error_code']) ? (string) $attributes['error_code'] : null,
            failureReason: isset($attributes['error_description']) ? (string) $attributes['error_description'] : null,
        );
    }
}
