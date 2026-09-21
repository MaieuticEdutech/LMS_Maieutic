<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\Settings\BrandingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your payment did not go through" (phases.md Phase 11's "PaymentFailed",
 * unblocked by Phase 12).
 *
 * SENT TO AN ANONYMOUS ROUTE, NOT A User (see
 * {@see \App\Actions\Payment\RecordFailedPayment}'s dispatch site) — a
 * failed payment very often belongs to someone with no account at all yet
 * (`orders.user_id` is only set on a SUCCESSFUL settlement,
 * {@see \App\Actions\Payment\SettleCapturedPayment}). `$notifiable` here is
 * therefore never assumed to carry a name, unlike every other notification
 * in this application.
 */
class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $orderNumber,
        private readonly string $courseTitle,
        private readonly string $courseSlug,
        private readonly ?string $reason,
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
        $branding = app(BrandingService::class);
        $organisation = $branding->organisationName();

        $message = (new MailMessage)
            ->subject("We couldn't complete your payment for {$this->courseTitle}")
            ->greeting('Hello')
            ->line("Your payment for **{$this->courseTitle}** (order {$this->orderNumber}) was not successful.");

        if ($this->reason !== null && $this->reason !== '') {
            $message->line("The payment provider reported: {$this->reason}");
        }

        return $message
            ->line('No amount has been charged. You can try again whenever you are ready.')
            ->action('Try again', url(route('catalogue.show', ['course' => $this->courseSlug], absolute: false)))
            ->line('If this keeps happening, reply to this message and we will help.')
            ->salutation("— The {$organisation} team")
            ->replyTo($branding->supportEmail());
    }
}
