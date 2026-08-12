<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

/**
 * Authorisation for user management (FR-ADM-07, FR-ADM-08, FR-RBAC-07…09).
 *
 * Deny-by-default: every method returns false unless a rule explicitly allows
 * the action (NFR-SEC-18).
 *
 * THREE INVARIANTS THIS POLICY EXISTS TO PROTECT:
 *
 *   1. Only a super admin manages users. No self-service path may create or
 *      elevate to a privileged role (FR-RBAC-07).
 *
 *   2. Nobody changes their OWN role or status — not even a super admin
 *      (FR-RBAC-08). A super admin who could demote themselves could lock the
 *      organisation out by accident; more importantly, an attacker who
 *      compromises any account should not be able to escalate it from within.
 *
 *   3. The last active super admin cannot be deleted, deactivated or demoted
 *      (FR-RBAC-09, AC-06). Losing the final administrator means losing
 *      control of the platform with no in-application recovery path.
 *
 * The Phase 4 admin UI consumes these; they are written now so the rules exist
 * before any screen can violate them.
 */
final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(User $actor, User $target): bool
    {
        // Anyone may view their own record (the profile screen).
        return $actor->isSuperAdmin() || $actor->is($target);
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() || $actor->is($target);
    }

    /**
     * Change another user's role.
     *
     * Self-modification is refused before the super-admin check, so the rule
     * holds for super admins too (invariant 2).
     */
    public function changeRole(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        if (! $actor->isSuperAdmin()) {
            return false;
        }

        // Demoting the last remaining active super admin would leave the
        // platform unmanageable (invariant 3).
        return ! $this->isLastActiveSuperAdmin($target);
    }

    /**
     * Activate, deactivate or suspend an account.
     */
    public function changeStatus(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        if (! $actor->isSuperAdmin()) {
            return false;
        }

        return ! $this->isLastActiveSuperAdmin($target);
    }

    public function delete(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        if (! $actor->isSuperAdmin()) {
            return false;
        }

        return ! $this->isLastActiveSuperAdmin($target);
    }

    public function restore(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() && ! $actor->is($target);
    }

    /**
     * Permanent deletion is never permitted through the application.
     *
     * NFR-DATA-05: user records are soft-deleted so financial and audit
     * history stays intact. Hard deletion, if ever legally required, is a
     * documented manual procedure — not a button.
     */
    public function forceDelete(User $actor, User $target): bool
    {
        return false;
    }

    /**
     * Is this the only active super admin left?
     *
     * Counts ACTIVE super admins specifically: an inactive or suspended one
     * cannot log in, so it does not count as a way back into the platform.
     */
    private function isLastActiveSuperAdmin(User $target): bool
    {
        if (! $target->isSuperAdmin()) {
            return false;
        }

        if ($target->status !== UserStatus::Active) {
            return false;
        }

        $otherActiveSuperAdmins = User::query()
            ->where('role', UserRole::SuperAdmin)
            ->where('status', UserStatus::Active)
            ->whereKeyNot($target->getKey())
            ->count();

        return $otherActiveSuperAdmins === 0;
    }
}
