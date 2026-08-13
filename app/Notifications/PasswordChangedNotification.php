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
 * "Your password was changed" security notice (FR-MAIL-07, architecture.md §14).
 *
 * Dispatched by SendPasswordChangedNotification from both password paths — the
 * forgot-password reset and the profile change.
 *
 * THIS EMAIL IS THE CONTROL, NOT A COURTESY. It is how the legitimate owner
 * discovers that somebody else changed their password. That is the entire
 * reason it exists, and why it is sent on every successful change rather than
 * only on ones that look suspicious — the application cannot tell the
 * difference, which is precisely the problem.
 *
 * IT CARRIES NO LINK AND NO TOKEN, DELIBERATELY. An email offering a
 * one-click "this wasn't me" undo is an email worth forging, and it would hand
 * an attacker who can read the mailbox a way to reverse a legitimate change.
 * The recovery path is the ordinary password reset, which the user reaches by
 * navigating to the site themselves.
 *
 * It also never states the new password, the old one, or any part of either
 * (FR-MAIL-02, NFR-DATA-03).
 */
class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        /*
         * FR-MAIL-06: queued, never sent in the request. Named queue comes
         * from config so it cannot drift from the worker's drain list
         * (architecture.md §13).
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
        $supportEmail = $branding->supportEmail();

        return (new MailMessage)
            ->subject("Your {$organisation} password was changed")
            ->greeting("Hello, {$notifiable->name}")
            ->line("The password for your {$organisation} account was changed just now.")
            ->line('You have also been signed out on every other device.')
            ->line("**If this was not you, contact us immediately at {$supportEmail}.** Your account may have been accessed by someone else.")
            ->line('If it was you, no further action is needed.')
            ->salutation("— The {$organisation} team")
            ->replyTo($supportEmail);
    }
}
