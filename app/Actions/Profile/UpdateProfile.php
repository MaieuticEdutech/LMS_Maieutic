<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Update a user's own profile details (FR-STU-12, FR-USR-04).
 *
 * NOT student-specific, despite arriving in Phase 7. An instructor and an
 * administrator have a name and a phone number too, and the profile screen
 * sits outside the role-gated route groups for exactly that reason.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * EMAIL IS NOT HANDLED HERE, DELIBERATELY.
 *
 * Changing an email address changes the identity a person signs in with and
 * the address every notification reaches. It needs re-verification, and until
 * that completes the OLD address must keep working — otherwise a typo locks
 * someone out of their own account permanently.
 *
 * That is a different shape from "save my new phone number", so it is a
 * different action: `ChangeEmail`. Folding it in here would mean one method
 * where some fields save immediately and one starts a multi-step flow, and
 * the caller could not tell which had happened.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * `role` and `status` are not fillable (NFR-SEC-07), so a forged extra field
 * in the form post cannot reach them even by accident.
 */
final class UpdateProfile
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name?: string, phone?: string|null}  $input
     */
    public function handle(User $user, array $input): User
    {
        // Only the fields this action owns. Anything else in the payload is
        // ignored rather than trusted — email in particular, which has its
        // own action and its own verification flow.
        $changes = array_intersect_key($input, array_flip(['name', 'phone']));

        if ($changes === []) {
            return $user;
        }

        $before = $user->only(array_keys($changes));

        DB::transaction(static function () use ($user, $changes): void {
            $user->fill($changes)->save();
        });

        // Audited because "who changed this account's details, and when" is a
        // support question that gets asked, and an account whose name quietly
        // changed is a signal worth being able to investigate.
        $this->audit->record(
            action: 'profile.updated',
            actor: $user,
            subject: $user,
            changes: ['before' => $before, 'after' => $changes],
            description: 'Updated their own profile details.',
        );

        return $user;
    }
}
