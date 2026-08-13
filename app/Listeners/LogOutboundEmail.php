<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\EmailLog;
use App\Services\Mail\EmailLogger;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Email;

/**
 * Logs every outbound email to `email_logs` (FR-MAIL-10, architecture.md §14).
 *
 * WHY THE TRANSPORT EVENTS RATHER THAN THE MAILABLES: this listener sits at
 * the single point every email must pass through, so coverage does not depend
 * on the author of a future mailable remembering to add a log call. Mailables,
 * notifications and framework-generated mail are all captured identically.
 *
 * NOT QUEUED, DELIBERATELY. These events already fire inside the queued job
 * that sends the mail (mailables and notifications are ShouldQueue), so this
 * runs on the worker, not in the web request. Queueing the listener itself
 * would add a second job whose only purpose is to write one row, and would
 * lose the causal link to the send it describes.
 *
 * IMPORTANT: this listener must never throw. An exception here would fail the
 * send job for a reason that has nothing to do with delivery, and on the last
 * retry that would turn a logging bug into a lost email. EmailLogger swallows
 * its own write failures; this class keeps the same contract.
 */
final class LogOutboundEmail
{
    /**
     * Key under which the log row's id is carried on the message, so that
     * `MessageSent` can find the row `MessageSending` created.
     *
     * It travels on the message itself rather than in listener state because
     * a worker can interleave sends, and matching by recipient alone would
     * attribute the wrong outcome to the wrong row.
     */
    private const LOG_ID_HEADER = 'X-LMS-Email-Log-Id';

    public function __construct(private readonly EmailLogger $logger) {}

    /**
     * Record the attempt immediately before the transport is handed the message.
     */
    public function sending(MessageSending $event): void
    {
        $message = $event->message;

        $log = $this->logger->recordQueued(
            recipient: $this->firstRecipient($message),
            mailable: $this->mailableName($event->data),
            subject: $message->getSubject() ?? '(no subject)',
        );

        if ($log !== null) {
            $message->getHeaders()->addTextHeader(self::LOG_ID_HEADER, (string) $log->getKey());
        }
    }

    /**
     * Promote the attempt to `sent` once the transport has accepted it.
     */
    public function sent(MessageSent $event): void
    {
        $log = $this->resolveLog($event->message);

        if ($log !== null) {
            $this->logger->markSent($log);
        }
    }

    /**
     * Find the row created by `sending` for this message.
     */
    private function resolveLog(Email $message): ?EmailLog
    {
        $header = $message->getHeaders()->get(self::LOG_ID_HEADER);

        if ($header === null) {
            return null;
        }

        $id = (int) $header->getBodyAsString();

        return $id > 0 ? EmailLog::query()->find($id) : null;
    }

    /**
     * The primary recipient.
     *
     * Every transactional email in this system addresses exactly one person
     * (architecture.md §14 — there are no bulk or digest emails in V1), so the
     * first To address is the recipient rather than an arbitrary choice.
     */
    private function firstRecipient(Email $message): string
    {
        $to = $message->getTo();

        return $to === [] ? '(none)' : $to[0]->getAddress();
    }

    /**
     * The mailable or notification class behind this message, for support
     * queries like "did the activation email go out?".
     *
     * @param  array<string, mixed>  $data
     */
    private function mailableName(array $data): string
    {
        foreach (['__laravel_notification', 'mailable'] as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }

            if (is_object($value)) {
                return $value::class;
            }
        }

        return 'unknown';
    }
}
