<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a course purchase (architecture.md §6.4, §11). `Created` is
 * the state before a gateway order exists; `Pending` once Razorpay has
 * issued a `gateway_order_id` and checkout is in progress; `Paid` only ever
 * set from the webhook path (Phase 12), never from the browser.
 *
 * Backed values are stored in `orders.status` and enforced by a database
 * CHECK constraint (ADR-012).
 */
enum OrderStatus: string
{
    case Created = 'created';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * Human-readable label for admin screens (Phase 4 onward).
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }

    /**
     * The {@see \x-badge} variant this status renders as.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Pending, self::Created => 'brand',
            self::Failed, self::Cancelled => 'danger',
            self::Refunded => 'warning',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
