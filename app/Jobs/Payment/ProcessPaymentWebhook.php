<?php

declare(strict_types=1);

namespace App\Jobs\Payment;

use App\Actions\Payment\RecordFailedPayment;
use App\Actions\Payment\SettleCapturedPayment;
use App\Contracts\Payment\PaymentGateway;
use App\Enums\WebhookStatus;
use App\Models\WebhookEvent;
use App\Services\Audit\AuditLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Process one already-persisted, already signature-verified webhook
 * delivery (architecture.md §11.2's queue-side path, §13's job table).
 *
 * Dispatched by {@see \App\Http\Controllers\Webhooks\ProcessRazorpayWebhookController}
 * AFTER the `webhook_events` row exists — this job's whole job is to turn
 * that row into a settled payment or a recorded failure. It never touches
 * the raw request; everything it needs was captured at receipt time
 * (architecture.md §11.3 rule 6: the endpoint verifies, persists and
 * dispatches only).
 *
 * 5 tries, exponential backoff, on the `critical` queue — the exact shape
 * architecture.md §13's job table specifies for this job, because a
 * transient database or gateway hiccup here must not be treated the same as
 * a permanently malformed event.
 */
final class ProcessPaymentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60, 300, 900];

    public function __construct(private readonly int $webhookEventId)
    {
        $this->onQueue(config()->string('lms.queues.critical'));
    }

    public function handle(
        PaymentGateway $gateway,
        SettleCapturedPayment $settleCapturedPayment,
        RecordFailedPayment $recordFailedPayment,
        AuditLogger $audit,
    ): void {
        $webhookEvent = WebhookEvent::query()->find($this->webhookEventId);

        if ($webhookEvent === null) {
            // Nothing to process. Not an error worth retrying over — the row
            // this job was built for is simply gone.
            return;
        }

        if ($webhookEvent->status === WebhookStatus::Processed) {
            // Idempotency at the job level too, not only at
            // webhook_events.event_id's UNIQUE constraint: a job that is
            // retried by the queue after successfully committing but before
            // acknowledging must not re-run the settlement.
            return;
        }

        $webhookEvent->forceFill(['status' => WebhookStatus::Processing])->save();

        try {
            $event = $gateway->parseEvent($webhookEvent->payload);

            match (true) {
                $event->type === 'payment.captured' && $event->payment !== null => $settleCapturedPayment->handle($event->payment),
                $event->type === 'payment.failed' && $event->payment !== null => $recordFailedPayment->handle($event->payment),
                default => $this->ignore($webhookEvent, $event->type),
            };

            if ($webhookEvent->status !== WebhookStatus::Ignored) {
                // A single forceFill+save, not ->increment('attempts') —
                // Eloquent's increment() issues its own UPDATE for just that
                // column (plus updated_at) against the database directly,
                // bypassing the model's normal dirty-attribute save. Chaining
                // it after forceFill() left 'status' set in PHP memory but
                // never written to the row — the exact bug this Action's own
                // test suite caught: every one of this job's paths persisted
                // as whatever status preceded it, never the terminal one.
                $webhookEvent->forceFill([
                    'status' => WebhookStatus::Processed,
                    'processed_at' => now(),
                    'attempts' => $webhookEvent->attempts + 1,
                ])->save();
            }
        } catch (Throwable $e) {
            $webhookEvent->forceFill([
                'status' => WebhookStatus::Failed,
                'last_error' => $e->getMessage(),
                'attempts' => $webhookEvent->attempts + 1,
            ])->save();

            throw $e;
        }
    }

    /**
     * An event type this application does not act on — recorded, not
     * silently dropped (architecture.md §11.3 rule 5's "an unhandled event
     * is recorded"; {@see WebhookStatus::Ignored}'s own docblock). Refunds
     * (`refund.processed`) land here in V1: refunds are initiated manually
     * in the Razorpay dashboard (A-14), so there is no automated settlement
     * action for this application to call — only a record that the event
     * arrived.
     */
    private function ignore(WebhookEvent $webhookEvent, string $eventType): void
    {
        $webhookEvent->forceFill([
            'status' => WebhookStatus::Ignored,
            'processed_at' => now(),
            'attempts' => $webhookEvent->attempts + 1,
        ])->save();
    }

    /**
     * Every retry exhausted. AlertOnFailedJob (Phase 11) turns this into an
     * operational alert — a payment webhook that could not be processed
     * after 5 attempts over ~22 minutes is exactly the kind of failure that
     * must reach a person, not just a log line.
     */
    public function failed(Throwable $exception): void
    {
        $webhookEvent = WebhookEvent::query()->find($this->webhookEventId);

        $webhookEvent?->forceFill([
            'status' => WebhookStatus::Failed,
            'last_error' => $exception->getMessage(),
        ])->save();
    }
}
