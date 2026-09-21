<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Contracts\Payment\PaymentGateway;
use App\Enums\WebhookStatus;
use App\Http\Controllers\Controller;
use App\Jobs\Payment\ProcessPaymentWebhook;
use App\Models\WebhookEvent;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The one entry point Razorpay itself calls (architecture.md §11.2's
 * authoritative path, §11.3 rules 3, 4 and 6).
 *
 * THIS CONTROLLER DOES THREE THINGS AND NO MORE: verify the signature,
 * persist the delivery, dispatch a job. Settlement — touching an `Order`, a
 * `Payment`, or {@see \App\Actions\Enrollment\GrantEnrollment} — happens
 * nowhere in this class. That split exists so the endpoint Razorpay is
 * timing out against never waits on a database transaction, a locked row,
 * or an outbound email.
 *
 * NOT behind the `web` middleware group (bootstrap/app.php) — no session,
 * no CSRF token, because a payment gateway cannot present one. Authenticity
 * comes entirely from {@see PaymentGateway::verifyWebhookSignature()}
 * against the RAW body, read before anything touches it.
 */
final class ProcessRazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway, AuditLogger $audit): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature', '');

        if ($signature === '' || ! $gateway->verifyWebhookSignature($rawBody, $signature)) {
            $audit->recordSecurityEvent(
                action: 'security.webhook_invalid_signature',
                description: 'Rejected a Razorpay webhook delivery with an invalid or missing signature.',
            );

            return response()->json(['error' => 'invalid signature'], 400);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($rawBody, true) ?? [];

        $event = $gateway->parseEvent($payload);
        $eventId = $gateway->deriveEventId($event, $rawBody);

        try {
            // Wrapped in its own transaction — same reason
            // GrantEnrollment's Layer 2 wraps its INSERT (that Action's own
            // docblock): PostgreSQL aborts a transaction entirely on the
            // failing statement, and does not un-abort it just because
            // application code catches the exception. Without this, a
            // caught unique-violation here would leave EVERY later query
            // on the same connection failing with "current transaction is
            // aborted" — including this method's own response. DB::transaction()
            // rolls back (or rolls back to a savepoint, when already nested
            // inside one, as every Feature test is) the moment the closure
            // throws, so the catch block below runs against a clean
            // connection.
            $webhookEvent = DB::transaction(fn (): WebhookEvent => WebhookEvent::query()->create([
                'gateway' => 'razorpay',
                'event_id' => $eventId,
                'event_type' => $event->type,
                'payload' => $payload,
                'signature' => $signature,
                'status' => WebhookStatus::Received,
                'received_at' => now(),
            ]));
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                // The same delivery, retried by Razorpay. Already recorded,
                // already being (or already been) processed — acknowledge
                // without doing anything a second time (architecture.md
                // §11.2, "duplicate event_id -> 200 already handled").
                return response()->json(['status' => 'already handled']);
            }

            throw $e;
        }

        // Fast ack: the endpoint's only remaining job is to hand off, not to
        // wait for settlement (architecture.md §11.3 rule 6).
        ProcessPaymentWebhook::dispatch($webhookEvent->getKey());

        return response()->json(['status' => 'accepted']);
    }

    /**
     * PostgreSQL SQLSTATE 23505 — unique_violation. Same check as
     * {@see \App\Actions\Enrollment\GrantEnrollment}'s own, for the same
     * reason: matched on the SQLSTATE so it survives a locale change or a
     * driver upgrade rewording the message.
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23505';
    }
}
