<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookEvent;

/**
 * Authorisation for the gateway webhook event log (architecture.md §13).
 *
 * Read-only, super-admin-only — same shape as {@see EmailLogPolicy} and for
 * the same reason: a row here is a record of a signature-verified delivery
 * from Razorpay, written only by
 * {@see \App\Http\Controllers\Webhooks\ProcessRazorpayWebhookController} and
 * {@see \App\Jobs\Payment\ProcessPaymentWebhook}. An editable webhook log
 * would carry the authority of evidence — "did this payment notification
 * really arrive, and what did it say" — while providing none of the
 * guarantee.
 *
 * No instructor branch, unlike OrderPolicy/PaymentPolicy: this table has no
 * student-visible half at all (a webhook delivery is not "my" anything), so
 * there is nothing narrower than super-admin-only to carve out.
 *
 * Registered by naming convention (WebhookEvent → WebhookEventPolicy).
 * tests/Feature/PolicyRegistrationTest.php asserts the resolution rather
 * than assuming it — auto-discovery fails silently.
 */
final class WebhookEventPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(User $actor, WebhookEvent $webhookEvent): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, WebhookEvent $webhookEvent): bool
    {
        return false;
    }

    public function delete(User $actor, WebhookEvent $webhookEvent): bool
    {
        return false;
    }
}
