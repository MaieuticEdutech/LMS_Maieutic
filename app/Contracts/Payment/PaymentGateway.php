<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

use App\Models\Order;
use App\Support\Money;
use App\Support\Payment\GatewayEvent;
use App\Support\Payment\GatewayOrder;
use App\Support\Payment\GatewayPayment;

/**
 * The gateway abstraction (architecture.md §11.1).
 *
 * All Razorpay-specific SDK usage, signature algorithm, event names and
 * amount units live inside {@see \App\Services\Payment\RazorpayGateway}.
 * Every other class in this application — checkout, the webhook job,
 * reconciliation — depends on this interface only, so adding a second
 * gateway later is one new implementation plus a config switch, not a
 * rewrite of the purchase flow.
 *
 * {@see \App\Services\Payment\FakeGateway} is the only implementation the
 * test suite ever binds. No test calls Razorpay.
 */
interface PaymentGateway
{
    /**
     * Create the gateway's own order for a local {@see Order}.
     *
     * The amount is read from the order, never accepted as a parameter here
     * — the caller ({@see \App\Actions\Payment\InitiateCheckout}) is where
     * "the browser never sets the price" (architecture.md §11.3 rule 1) is
     * enforced, by reading `courses.price_amount` before this is ever
     * called.
     */
    public function createOrder(Order $order): GatewayOrder;

    /**
     * Verify a webhook delivery's signature against the RAW request body.
     *
     * Must be constant-time (architecture.md §11.3 rule 3) — an
     * implementation that uses `===` on the computed digest defeats the
     * point of verifying a signature at all.
     */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool;

    /**
     * Parse a verified webhook payload into the gateway-neutral shape.
     *
     * Called only after {@see self::verifyWebhookSignature()} has returned
     * true — an implementation is free to assume the payload is
     * authentic.
     *
     * @param  array<string, mixed>  $payload
     */
    public function parseEvent(array $payload): GatewayEvent;

    /**
     * Fetch a payment directly from the gateway, by its own payment id.
     *
     * Used by reconciliation ({@see \App\Console\Commands\ReconcileOrders})
     * to settle orders whose webhook never arrived — the self-healing path
     * architecture.md §11.3 rule 8 requires.
     */
    public function fetchPayment(string $gatewayPaymentId): GatewayPayment;

    /**
     * Fetch an order directly from the gateway, by its own order id.
     */
    public function fetchOrder(string $gatewayOrderId): GatewayOrder;

    /**
     * Every payment attempt the gateway has recorded against an order.
     *
     * {@see \App\Console\Commands\ReconcileOrders} is the caller: Razorpay's
     * order object carries a status and an amount paid, but reconciling a
     * `pending` local order into a settled one needs an actual payment id to
     * hand to {@see \App\Actions\Payment\SettleCapturedPayment} — the same
     * shape the webhook path settles from — so the self-healing sweep asks
     * the gateway for the order's payments directly rather than inventing a
     * different settlement path for the reconciliation case.
     *
     * @return list<GatewayPayment>
     */
    public function fetchOrderPayments(string $gatewayOrderId): array;

    /**
     * The minor-unit amount this gateway expects, for the currency the
     * order was created in.
     *
     * A pass-through in V1 (Razorpay already uses paise — see
     * {@see Money}'s own docblock) but named as its own method rather than
     * assumed equal to {@see Money::$amount} everywhere the gateway is
     * called, so a future gateway with a different minor-unit convention is
     * a single override, not an audit of every call site.
     */
    public function toGatewayAmount(Money $money): int;

    /**
     * The idempotency key {@see \App\Http\Controllers\Webhooks\ProcessRazorpayWebhookController}
     * writes to `webhook_events.event_id` (UNIQUE) for this delivery.
     *
     * Not every gateway hands the receiving endpoint a ready-made delivery
     * id — {@see \App\Services\Payment\RazorpayGateway}'s docblock explains
     * why Razorpay does not — so this composes one from values the parsed
     * {@see GatewayEvent} carries (falling back to a hash of the raw body
     * when there is no payment to key on), rather than the controller
     * reaching into gateway-specific payload shape itself.
     */
    public function deriveEventId(GatewayEvent $event, string $rawBody): string;
}
