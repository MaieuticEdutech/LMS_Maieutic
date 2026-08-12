<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Authorisation for the audit log (FR-ADM-15, NFR-SEC-17).
 *
 * Read-only, super-admin-only, and IMMUTABLE BY EVERYONE.
 *
 * `create`, `update`, `delete` and `restore` all return false — including for
 * a super admin. An audit entry is a side effect of an action, written by
 * AuditLogger; it is never something a user does. If any of these returned
 * true, the log would become a table that an attacker with sufficient
 * privilege could edit to erase their own tracks, which would make it worse
 * than useless: it would carry the authority of evidence while providing none
 * of the guarantee.
 *
 * The AuditLog model backs this up at the ORM level by throwing on update and
 * delete, so the invariant holds even for code that never consults a policy.
 */
final class AuditLogPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(User $actor, AuditLog $auditLog): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $actor, AuditLog $auditLog): bool
    {
        return false;
    }

    public function restore(User $actor, AuditLog $auditLog): bool
    {
        return false;
    }

    public function forceDelete(User $actor, AuditLog $auditLog): bool
    {
        return false;
    }
}
