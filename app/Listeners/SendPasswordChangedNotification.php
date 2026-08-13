<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Auth\Events\PasswordReset;
use Laravel\Fortify\Events\PasswordUpdatedViaController;

/**
 * Tells a user their password changed (FR-MAIL-07, architecture.md §14).
 *
 * WHY IT IS A LISTENER: the single implementation behind both password paths
 * is `ChangeUserPassword`, which belongs to Track A. Sending from here keeps
 * Track C's email concern out of Track A's action, and keeps the action doing
 * one thing.
 *
 * HANDLES BOTH PASSWORD EVENTS. Fortify dispatches `PasswordReset` for the
 * forgot-password flow and `PasswordUpdatedViaController` for the profile
 * screen; a user whose password is changed by either route gets the notice.
 *
 * THIS EMAIL IS A SECURITY CONTROL, NOT A COURTESY. It is how a user finds out
 * that someone else changed their password, so it is sent even though the act
 * was, as far as the application knows, legitimate. It carries no link and no
 * token: an email that offers a "wasn't me" button is an email worth forging.
 */
final class SendPasswordChangedNotification
{
    public function handle(PasswordReset|PasswordUpdatedViaController $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $user->notify(new PasswordChangedNotification);
    }
}
