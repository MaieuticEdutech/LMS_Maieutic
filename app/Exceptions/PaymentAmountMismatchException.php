<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Order;
use DomainException;

/**
 * A captured payment whose amount or currency does not match the order it
 * claims to settle.
 *
 * A DomainException so every existing handler (the webhook job, the failed-job
 * alert) treats it exactly as before. It exists so SettleCapturedPayment can
 * carry the order out of its database transaction: the audit entry for a
 * possible tampering attempt has to be written AFTER the rollback, or the
 * rollback takes the entry with it.
 */
final class PaymentAmountMismatchException extends DomainException
{
    public function __construct(
        string $message,
        public readonly Order $order,
        public readonly string $auditDescription,
    ) {
        parent::__construct($message);
    }
}
