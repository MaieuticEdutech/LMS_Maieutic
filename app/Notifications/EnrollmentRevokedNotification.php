<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Services\Settings\BrandingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your access to <course> has ended" (FR-MAIL-07, architecture.md §14).
 *
 * Dispatched by SendEnrollmentRevokedNotification from the `EnrollmentRevoked`
 * event, which covers every route to losing access: an admin revoking, a
 * refund, or scheduled expiry.
 *
 * ACCESS IS ALREADY GONE BEFORE THIS IS SENT. The event fires after the
 * revocation transaction commits, so this email never gates anything — if it
 * fails, the student is still correctly locked out. It informs; it does not
 * enforce.
 *
 * TONE IS PART OF THE REQUIREMENT. This email reaches someone who may have
 * just been refunded, or whose paid access has simply run out. It states what
 * happened, why, and how to get in touch — and does not apologise, accuse, or
 * try to sell anything.
 *
 * AUTOMATIC EXPIRY IS PHRASED DIFFERENTLY FROM A DELIBERATE REVOCATION.
 * "An administrator has removed your access" is alarming and wrong when the
 * truth is that a time-limited enrollment reached its end date, and that
 * distinction is exactly what `wasAutomatic` carries.
 *
 * Payload is scalars, not the model — see EnrollmentGrantedNotification for
 * why the facts must be captured at event time rather than re-read on the
 * worker. It matters more here: the enrollment this describes is, by
 * definition, no longer active.
 */
class EnrollmentRevokedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $courseTitle,
        private readonly string $reason,
        private readonly bool $wasAutomatic = false,
    ) {
        $this->onQueue(config()->string('lms.queues.mail'));
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $branding = app(BrandingService::class);
        $organisation = $branding->organisationName();
        $supportEmail = $branding->supportEmail();

        return (new MailMessage)
            ->subject("Your access to {$this->courseTitle} has ended")
            ->greeting("Hello, {$notifiable->name}")
            ->line($this->wasAutomatic
                ? "Your access period for **{$this->courseTitle}** has now ended."
                : "Your access to **{$this->courseTitle}** at {$organisation} has been withdrawn.")
            ->line("Reason: {$this->reason}")
            ->line("If you think this is a mistake, or you would like to regain access, contact us at {$supportEmail} and we will look into it.")
            ->salutation("— The {$organisation} team")
            ->replyTo($supportEmail);
    }
}
