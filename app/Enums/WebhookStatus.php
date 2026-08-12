<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Processing state of a received gateway webhook (architecture.md §6.4,
 * §13). `Received` is the state a signature-verified event lands in before
 * `ProcessPaymentWebhook` picks it up; `Ignored` covers event types the
 * application does not act on (e.g. a Razorpay event outside the handled
 * set) — recorded rather than silently dropped, so nothing is invisible.
 *
 * Backed values are stored in `webhook_events.status` and enforced by a
 * database CHECK constraint (ADR-012).
 */
enum WebhookStatus: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
    case Ignored = 'ignored';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
