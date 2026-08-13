<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Services\Settings\BrandingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Email verification link for self-registered students (FR-AUTH-11).
 *
 * Until this is clicked the account sits at `pending_verification` and cannot
 * authenticate — the status check inside Fortify::authenticateUsing() refuses
 * anything that is not `active`. Clicking it fires Laravel's Verified event,
 * which ActivateUserAfterEmailVerification turns into the status transition.
 *
 * Uses a SIGNED, expiring URL: the signature is derived from the user id and
 * a hash of their email address, so the link cannot be altered to verify a
 * different account or a changed address.
 */
class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        // PHASE 11 (FR-MAIL-06): queued on the named mail queue, never sent
        // inside the registration request.
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

        $url = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(config()->integer('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1((string) $notifiable->getEmailForVerification()),
            ],
        );

        return (new MailMessage)
            ->subject("Verify your email address for {$organisation}")
            ->greeting("Hello, {$notifiable->name}")
            ->line("Thanks for registering with {$organisation}. Please confirm your email address to activate your account.")
            ->action('Verify email address', $url)
            ->line('If you did not create an account, you can safely ignore this message.')
            ->salutation("— The {$organisation} team")
            ->replyTo($branding->supportEmail());
    }
}
