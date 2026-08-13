<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;

/**
 * Raises an alert when a job exhausts its retries (phases.md Phase 11,
 * architecture.md §13, §20).
 *
 * WHY THIS MATTERS MORE THAN IT LOOKS: by the time this fires, the work is
 * NOT going to happen on its own. For `SendMailJob` that means a student never
 * received an activation email; from Phase 12, for `ProcessPaymentWebhook`, it
 * means someone paid and did not get access. Both are silent failures — the
 * user sees nothing, and without an alert neither does anyone else.
 *
 * TRANSPORT: `Log::critical` today, which in production is the channel Phase 17
 * routes to real alerting (architecture.md §20). Choosing the destination is a
 * Phase 16/17 decision (PD-07 applies to mail; the alerting sink is the same
 * shape of decision), so this deliberately does not name a provider — it emits
 * a structured, severity-tagged record and lets configuration decide where it
 * goes.
 *
 * NEVER THROWS: an exception raised while reporting a failure would replace a
 * useful failure with a confusing one, and Laravel would then be handling an
 * exception inside its own failure path.
 */
final class AlertOnFailedJob
{
    public function handle(JobFailed $event): void
    {
        Log::critical('Queued job failed after exhausting its retries.', [
            'job' => $event->job->resolveName(),
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'attempts' => $event->job->attempts(),

            /*
             * The exception class and message only — never the job payload.
             * Payloads carry email addresses and, from Phase 12, order and
             * payment identifiers; an alerting sink is not an appropriate
             * place for personal or financial data (NFR-DATA-03).
             */
            'exception' => $event->exception::class,
            'message' => $event->exception->getMessage(),
        ]);
    }
}
