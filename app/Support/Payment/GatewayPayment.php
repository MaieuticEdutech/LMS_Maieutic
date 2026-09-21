<?php

declare(strict_types=1);

namespace App\Support\Payment;

/**
 * A payment gateway's own payment object (architecture.md §11.1).
 *
 * `orderId` is the gateway's order identifier, not this application's
 * `orders.id` — {@see \App\Actions\Payment\SettleCapturedPayment} resolves
 * the local {@see \App\Models\Order} by matching `gateway_order_id` against
 * this field, never the other way around.
 */
final readonly class GatewayPayment
{
    public function __construct(
        public string $id,
        public string $orderId,
        public int $amount,
        public string $currency,
        public string $status,
        public ?string $method,
        public ?string $failureCode,
        public ?string $failureReason,
    ) {}
}
