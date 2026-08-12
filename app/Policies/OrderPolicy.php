<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Authorisation for order visibility (architecture.md §8.3, FR-INS-10).
 *
 * Deny-by-default (NFR-SEC-18). Orders are never created or updated through
 * a policy-gated user action — the checkout flow and webhook processing
 * (Phase 12, Govind's territory) write them directly — so this policy only
 * ever needs to answer "who may look at this."
 *
 * THE RULE THAT MAY NEVER BE RELAXED: an instructor never sees a financial
 * figure anywhere in this system (FR-INS-10). This is checked FIRST and
 * unconditionally, before the super-admin check, so it cannot be
 * accidentally bypassed by broadening `isSuperAdmin()` or by an instructor
 * who also happens to be the buyer.
 */
final class OrderPolicy
{
    public function viewAny(User $actor): bool
    {
        if ($actor->isInstructor()) {
            return false;
        }

        return $actor->isSuperAdmin();
    }

    public function view(User $actor, Order $order): bool
    {
        if ($actor->isInstructor()) {
            return false;
        }

        if ($actor->isSuperAdmin()) {
            return true;
        }

        return $actor->isStudent() && $actor->is($order->user);
    }
}
