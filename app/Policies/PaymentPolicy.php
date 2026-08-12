<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Authorisation for payment visibility (architecture.md §8.3, FR-INS-10).
 *
 * Deny-by-default (NFR-SEC-18). Same shape as `OrderPolicy` and for the same
 * reason: payments are never created or updated through a policy-gated user
 * action — the checkout flow and webhook processing (Phase 12, Govind's
 * territory) write them directly.
 *
 * THE RULE THAT MAY NEVER BE RELAXED: an instructor never sees a financial
 * figure anywhere in this system (FR-INS-10). Checked first and
 * unconditionally, before the super-admin check, for the same reason as
 * `OrderPolicy`.
 */
final class PaymentPolicy
{
    public function viewAny(User $actor): bool
    {
        if ($actor->isInstructor()) {
            return false;
        }

        return $actor->isSuperAdmin();
    }

    public function view(User $actor, Payment $payment): bool
    {
        if ($actor->isInstructor()) {
            return false;
        }

        if ($actor->isSuperAdmin()) {
            return true;
        }

        return $actor->isStudent() && $actor->is($payment->order?->user);
    }
}
