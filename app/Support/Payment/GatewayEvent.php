<?php

declare(strict_types=1);

namespace App\Support\Payment;

/**
 * One parsed webhook delivery (architecture.md §11.1, §11.2).
 *
 * `type` is the gateway's own event name (e.g. `payment.captured`,
 * `payment.failed`) — {@see \App\Jobs\Payment\ProcessPaymentWebhook}
 * branches on it, never on the raw payload shape, so a gateway's field
 * naming never leaks past {@see \App\Contracts\Payment\PaymentGateway}.
 *
 * `payment` is null for event types this application does not carry a
 * {@see GatewayPayment} for; the job records those as
 * {@see \App\Enums\WebhookStatus::Ignored} rather than guessing at them
 * (architecture.md §11.3 rule 5 — an unhandled event is recorded, not
 * silently dropped).
 */
final readonly class GatewayEvent
{
    public function __construct(
        public string $type,
        public ?GatewayPayment $payment,
    ) {}
}
