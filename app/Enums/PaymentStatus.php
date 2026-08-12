<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of one payment attempt against an order (architecture.md §6.4,
 * §11). Separate from `OrderStatus` because one order legitimately has
 * several payment attempts — fail, retry, capture — and Razorpay models
 * order and payment as distinct objects.
 *
 * Backed values are stored in `payments.status` and enforced by a database
 * CHECK constraint (ADR-012).
 */
enum PaymentStatus: string
{
    case Created = 'created';
    case Authorized = 'authorized';
    case Captured = 'captured';
    case Failed = 'failed';
    case Refunded = 'refunded';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
