<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\Settings\BrandingService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The purchase receipt (phases.md Phase 11's "PurchaseConfirmation",
 * unblocked by Phase 12's verified payment).
 *
 * DISTINCT FROM {@see EnrollmentGrantedNotification}, which
 * `GrantEnrollment`'s own event already sends for every grant — admin or
 * purchased alike. That email answers "what can I now reach"; this one
 * answers "what did I pay, and for what" — an order number and an amount, the
 * record a buyer expects after a transaction regardless of whether they also
 * received a separate "you have access" message. Both are sent for a
 * purchase; only this one for an admin grant would be a receipt for money
 * nobody paid.
 *
 * WHY THE PAYLOAD IS SCALARS, NOT THE ORDER MODEL — same reasoning as
 * EnrollmentGrantedNotification's own docblock: a queued notification
 * serialises its constructor arguments, and the facts of what was paid must
 * travel with the job as they were at the moment of payment, not be
 * re-fetched from a row that could theoretically change under it.
 */
class PurchaseConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $orderNumber,
        private readonly string $courseTitle,
        private readonly Money $amountPaid,
        private readonly CarbonImmutable $paidAt,
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

        return (new MailMessage)
            ->subject("Your receipt for {$this->courseTitle}")
            ->greeting('Thank you for your purchase')
            ->line("We've received your payment of **{$this->amountPaid}** for **{$this->courseTitle}**.")
            ->line("Order number: {$this->orderNumber}")
            ->line("Date: {$this->paidAt->toFormattedDayDateString()}")
            ->action('Go to my courses', url(route('student.home', absolute: false)))
            ->line('Keep this email as your receipt for this purchase.')
            ->salutation("— The {$organisation} team")
            ->replyTo($branding->supportEmail());
    }
}
