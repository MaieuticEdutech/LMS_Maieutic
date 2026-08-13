<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmailLog;
use App\Models\User;

/**
 * Authorisation for the outbound email log (FR-MAIL-10).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * READ-ONLY, SUPER-ADMIN-ONLY, AND MORE SENSITIVE THAN IT FIRST APPEARS.
 *
 * Every row names a person and states what was sent to them: who was enrolled,
 * who reset a password, who failed an assessment. Taken together the table is a
 * timeline of individual students' activity — closer to the audit log in
 * sensitivity than to a system metric, and treated the same way.
 *
 * `create`, `update` and `delete` all return false, including for a super
 * admin. Rows are written by LogOutboundEmail from the mail transport events;
 * they are a record of something that happened, not something a user does. An
 * editable delivery log would carry the authority of evidence while providing
 * none of the guarantee — the same reasoning as AuditLogPolicy.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * Registered by naming convention (EmailLog → EmailLogPolicy). PolicyRegistrationTest
 * asserts the resolution rather than assuming it: auto-discovery fails silently.
 */
final class EmailLogPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(User $actor, EmailLog $emailLog): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, EmailLog $emailLog): bool
    {
        return false;
    }

    public function delete(User $actor, EmailLog $emailLog): bool
    {
        return false;
    }
}
