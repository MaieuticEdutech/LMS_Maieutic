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
     * @param  array{name?: string, first_name?: string|null, last_name?: string|null, certificate_name?: string|null, phone?: string|null}  $input
     */
    public function handle(User $user, array $input): User
    {
        // Only the fields this action owns. Anything else in the payload is
        // ignored rather than trusted — email in particular, which has its
        // own action and its own verification flow.
        $changes = array_intersect_key($input, array_flip([
            'name', 'first_name', 'last_name', 'certificate_name', 'phone',
        ]));

        if ($changes === []) {
            return $user;
        }

        /*
         * ═════════════════════════════════════════════════════════════════
         * `name` IS DERIVED FROM THE PARTS HERE, AND ONLY HERE.
         *
         * The learner edits first and last name; the rest of the system reads
         * `name` — every transactional email greeting, the admin tables, the
         * instructor's student lists. If the two were allowed to drift, a
         * person could rename themselves on this screen and still be greeted
         * by the old name in their next email, which reads as the system not
         * having listened.
         *
         * One writer, so there is never a question of which is authoritative.
         * The parts are the input; `name` is the projection.
         *
         * Guarded by assembledName() returning null when both parts are blank:
         * a payload carrying empty strings must not wipe a display name that
         * is currently correct.
         * ═════════════════════════════════════════════════════════════════
         */
        if (array_key_exists('first_name', $changes) || array_key_exists('last_name', $changes)) {
            $projected = (clone $user)->fill($changes)->assembledName();

            if ($projected !== null) {
                $changes['name'] = $projected;
            }
        }

        /*
         * Deliberately NOT $user->only(). These columns can be absent rather
         * than null on a model whose row was just inserted (see
         * User::nameField), and only() reads each one through getAttribute,
         * which throws under preventAccessingMissingAttributes.
         *
         * Absent is recorded as null so the before/after pair in the audit
         * entry always carries the same keys — a diff missing a key on one side
         * is unreadable to whoever is investigating.
         */
        $attributes = $user->getAttributes();
        $before = [];

        foreach (array_keys($changes) as $column) {
            $before[$column] = $attributes[$column] ?? null;
        }

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
