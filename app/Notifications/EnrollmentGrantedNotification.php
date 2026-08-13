<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Services\Settings\BrandingService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "You now have access to <course>" (FR-MAIL-07, architecture.md §14).
 *
 * Dispatched by SendEnrollmentGrantedNotification from the `EnrollmentGranted`
 * event, which `GrantEnrollment` (Track A) emits on every real access grant —
 * admin grant, verified purchase, or reactivation.
 *
 * WHY THE PAYLOAD IS SCALARS, NOT THE ENROLLMENT MODEL:
 * Queued notifications serialise their constructor arguments. Passing the model
 * would re-fetch it on the worker, so the email would describe the enrollment's
 * state at SEND time rather than at GRANT time. If a student were granted and
 * then immediately revoked, the "welcome" email would arrive describing a
 * revoked enrollment — or fail outright if the row had been deleted. The facts
 * are captured when the event fires and travel with the job (FR-SYS-04).
 *
 * REACTIVATION READS DIFFERENTLY. A first grant is a welcome; restoring access
 * to someone who previously had it is not, and telling a returning student
 * "welcome to the course" for the second time is the kind of small wrongness
 * that makes automated mail feel careless.
 */
class EnrollmentGrantedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $courseTitle,
        private readonly bool $wasReactivated = false,
        private readonly ?CarbonImmutable $expiresAt = null,
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

        $message = (new MailMessage)
            ->subject($this->wasReactivated
                ? "Your access to {$this->courseTitle} has been restored"
                : "You now have access to {$this->courseTitle}")
            ->greeting("Hello, {$notifiable->name}")
            ->line($this->wasReactivated
                ? "Your access to **{$this->courseTitle}** has been restored. You can pick up where you left off."
                : "You have been enrolled in **{$this->courseTitle}** at {$organisation}.")
            ->action('Go to my courses', url(route('student.home', absolute: false)));

        /*
         * Only stated when there is one. A permanent enrollment must not carry
         * an empty or invented expiry line — a student reading "expires on" and
         * finding no date is worse than no mention at all.
         */
        if ($this->expiresAt !== null) {
            $message->line("Your access runs until {$this->expiresAt->toFormattedDayDateString()}.");
        }

        return $message
            ->line('If you have any trouble reaching the course, reply to this message and we will help.')
            ->salutation("— The {$organisation} team")
            ->replyTo($branding->supportEmail());
    }
}
