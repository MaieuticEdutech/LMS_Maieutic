<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Services\Settings\BrandingService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Password reset link (FR-AUTH-04).
 *
 * Replaces Laravel's generic notification so the email carries the
 * organisation's identity from BrandingService rather than config('app.name')
 * (rule S-1, FR-MAIL-08).
 *
 * Deliberately vague about whether anything was wrong: the message never says
 * "your account exists" beyond the fact it arrived, and the endpoint that
 * triggers it returns the same response whether or not the address is known,
 * so it cannot be used to enumerate accounts.
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

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

        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], absolute: false));

        $expiresInMinutes = config()->integer('lms.auth.password_reset_ttl', 60);

        return (new MailMessage)
            ->subject("Reset your {$organisation} password")
            ->greeting("Hello, {$notifiable->name}")
            ->line("We received a request to reset the password for your {$organisation} account.")
            ->action('Reset password', $url)
            ->line("This link expires in {$expiresInMinutes} minutes and can only be used once.")
            ->line('If you did not request a password reset, no action is required — your password has not changed.')
            ->salutation("— The {$organisation} team")
            ->replyTo($branding->supportEmail());
    }
}
