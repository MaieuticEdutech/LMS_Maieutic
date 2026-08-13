<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Enums\EmailStatus;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records every outbound email attempt (FR-MAIL-10, architecture.md §14).
 *
 * WHY THIS EXISTS: when a student says "I never got my activation email", the
 * only useful answer comes from a record of what the system tried to send and
 * what happened. Without it, support is guesswork and a silently misconfigured
 * transport looks exactly like a working one.
 *
 * CALLED FROM THE MAIL EVENT LISTENERS, NOT FROM MAILABLES. Hooking Laravel's
 * MessageSending / MessageSent events means every email is logged — mailables,
 * notifications, and anything a future phase adds — with no obligation on the
 * author of a new email to remember to log it. A logging rule that depends on
 * being remembered is a rule that is eventually broken.
 *
 * THIS SERVICE NEVER THROWS (see `safely`). Logging is observability, not the
 * delivery itself: a failure to write the log must not fail the send, and must
 * certainly not fail the transaction that triggered the send (AC-33).
 */
final class EmailLogger
{
    /**
     * Record that an email is about to be handed to the transport.
     *
     * @param  array<string, mixed>  $context
     */
    public function recordQueued(string $recipient, string $mailable, string $subject, array $context = []): ?EmailLog
    {
        return $this->safely(static fn (): EmailLog => EmailLog::create([
            'to_email' => $recipient,
            'mailable' => $mailable,
            'subject' => $subject,
            'status' => EmailStatus::Queued,
            'context' => $context === [] ? null : $context,
        ]));
    }

    /**
     * Mark a previously recorded attempt as delivered to the transport.
     *
     * "Sent" means the transport accepted it, which is the furthest the
     * application can honestly claim to know. Whether it reached an inbox is
     * a question only the provider can answer, and pretending otherwise would
     * make this log a liar in exactly the support conversation it exists for.
     */
    public function markSent(EmailLog $log): void
    {
        $this->safely(static function () use ($log): EmailLog {
            $log->forceFill([
                'status' => EmailStatus::Sent,
                'sent_at' => now(),
                'error' => null,
            ])->save();

            return $log;
        });
    }

    /**
     * Mark an attempt as failed, keeping the transport's reason.
     */
    public function markFailed(EmailLog $log, string $error): void
    {
        $this->safely(static function () use ($log, $error): EmailLog {
            $log->forceFill([
                'status' => EmailStatus::Failed,

                // Bounded: a provider stack trace can run to kilobytes and the
                // useful part is always at the front.
                'error' => mb_substr($error, 0, 2000),
            ])->save();

            return $log;
        });
    }

    /**
     * Run a log write, swallowing any failure into the application log.
     *
     * @template T
     *
     * @param  callable(): T  $write
     * @return T|null
     */
    private function safely(callable $write): mixed
    {
        try {
            return $write();
        } catch (Throwable $e) {
            // The application log is the fallback of last resort. If even this
            // is failing the operator has a larger problem, but the email —
            // and the transaction behind it — still completes.
            Log::error('Failed to write email log entry.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
