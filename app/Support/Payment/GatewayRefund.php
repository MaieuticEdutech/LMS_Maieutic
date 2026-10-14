<?php

declare(strict_types=1);

namespace App\Support\Payment;

/**
 * A refund as the gateway reports it, in the gateway's own units (paise).
 *
 * Gateway-neutral on purpose, like GatewayPayment: the action that applies a
 * refund must not know it came from Razorpay.
 */
final readonly class GatewayRefund
{
    public function __construct(
        public string $id,
        public string $paymentId,
        public int $amount,
        public string $status,
    ) {}
}
