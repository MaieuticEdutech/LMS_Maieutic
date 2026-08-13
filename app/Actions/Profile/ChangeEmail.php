<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Change the address a user signs in with, and re-verify it (FR-AUTH-12).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * THE ADDRESS CHANGES IMMEDIATELY; VERIFICATION IS CLEARED WITH IT.
 *
 * Two designs are possible and only one is honest.
 *
 * The tempting one holds the new address in a pending column, keeps the old
 * one live, and swaps them when the link is clicked. It sounds safer. It also
 * means the database has two email columns, every query has to know which is
 * authoritative, and a half-finished change can sit there for months.
 *
 * This takes the simpler path: write the new address, set
 * `email_verified_at` to null, and send a fresh verification link. The user
 * is now unverified until they confirm — which is exactly what "we do not yet
 * know this address is yours" means.
 *
 * The consequence is real and worth stating: mistype the address and you have
 * locked yourself out, because the link goes to the address you typed. That
 * is why the UI asks for the current password first — the confirmation step
 * is the protection, not a pending column.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * STATUS IS UNTOUCHED. A student mid-course who changes their email does not
 * become `pending_verification` and lose access to content they paid for.
 * Verification state and account state are different things, and conflating
 * them would make a routine profile edit revoke access.
 */
final class ChangeEmail
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws InvalidArgumentException when the address is unchanged.
     */
    public function handle(User $user, string $newEmail): User
    {
        // Normalised the same way every other write path does, so a change
        // that differs only in case or whitespace is correctly a no-op rather
        // than an unnecessary re-verification.
        $newEmail = mb_strtolower(trim($newEmail));

        if ($newEmail === $user->email) {
            throw new InvalidArgumentException('That is already your email address.');
        }

        $previous = $user->email;

        DB::transaction(static function () use ($user, $newEmail): void {
            $user->forceFill([
                'email' => $newEmail,
                // Unverified until proven otherwise. This is the whole point.
                'email_verified_at' => null,
            ])->save();
        });

        // Sent after the write commits: a link pointing at an address the
        // database does not hold would fail verification and read as a broken
        // email to the user.
        $user->sendEmailVerificationNotification();

        // The old address is recorded. If an account is taken over and the
        // email changed, this entry is how it gets traced back — which is why
        // audit_logs is append-only (NFR-SEC-17).
        $this->audit->record(
            action: 'profile.email_changed',
            actor: $user,
            subject: $user,
            changes: ['before' => ['email' => $previous], 'after' => ['email' => $newEmail]],
            description: sprintf('Changed their email from %s to %s. Re-verification required.', $previous, $newEmail),
        );

        return $user;
    }
}
