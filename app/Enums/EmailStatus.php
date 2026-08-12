<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Delivery state of a logged outbound email (architecture.md §6.4, §14,
 * FR-MAIL-10). Written by `SendMailJob` after every mailable dispatch —
 * `Queued` on enqueue, then `Sent` or `Failed` once the worker runs it.
 *
 * Backed values are stored in `email_logs.status` and enforced by a
 * database CHECK constraint (ADR-012).
 */
enum EmailStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
