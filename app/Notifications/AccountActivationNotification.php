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
 * "Welcome — set your password" (FR-MAIL-02, FR-MAIL-03, FR-AUTH-05).
 *
 * Sent to an account created on the user's behalf — in Phase 12, by a verified
 * purchase. It is the ONLY way such an account becomes usable.
 *
 * WHAT THIS EMAIL MUST NEVER CONTAIN:
 * A password. Not a temporary one, not a generated one, not "please change
 * this after logging in". Email is not a confidential channel: it sits in
 * plaintext in mailboxes, backups and provider logs indefinitely. The user
 * chooses their own password behind a link that is hashed at rest, expires,
 * and dies on first use.
 *
 * Branding is resolved through BrandingService, never hardcoded, so V2 can
 * make it per-organisation without touching this class (rule S-1, FR-MAIL-08).
 */
class AccountActivationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token)
    {
        /*
         * PHASE 11 (FR-MAIL-06, AC-33): queued, never sent in the request.
         *
         * This is the email Phase 12 sends from inside the enrollment
         * transaction. Sending it synchronously would mean a slow or failing
         * mail transport could roll back a paid enrollment — the customer's
         * single most important guarantee, broken by an SMTP timeout.
         *
         * The token is a string, not a model, so the serialised payload is
         * explicit and carries no ambient state (FR-SYS-04).
         */
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

        $url = url(route('activate.show', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], absolute: false));

        $expiresInHours = (int) round(config()->integer('lms.auth.activation_link_ttl', 4320) / 60);

        return (new MailMessage)
            ->subject("Activate your {$organisation} account")
            ->greeting("Welcome, {$notifiable->name}")
            ->line("An account has been created for you at {$organisation}.")
            ->line('To get started, choose a password using the secure link below.')
            ->action('Set your password', $url)
            ->line("This link expires in {$expiresInHours} hours and can only be used once.")
            ->line('If the link has expired, you can request a new one from the login page.')
            ->salutation("— The {$organisation} team")
            ->replyTo($branding->supportEmail());
    }
}
