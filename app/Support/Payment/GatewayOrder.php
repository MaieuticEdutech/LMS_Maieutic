<?php

declare(strict_types=1);

namespace App\Support\Payment;

/**
 * A payment gateway's own order object (architecture.md §11.1).
 *
 * Domain code speaks only this shape, never Razorpay's raw array response.
 * Adding a second gateway later means one more class implementing
 * {@see \App\Contracts\Payment\PaymentGateway} that returns this same value
 * object — nothing above the gateway boundary changes.
 */
final readonly class GatewayOrder
{
    public function __construct(
        public string $id,
        public int $amount,
        public string $currency,
        public string $status,
    ) {}
}
