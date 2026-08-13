<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;

/**
 * Authorisation for organisation settings (FR-SYS-01, FR-ADM-16).
 *
 * Deny-by-default: every method returns false unless a rule explicitly
 * allows the action (NFR-SEC-18).
 *
 * Settings drive branding, mail sender identity and learning thresholds
 * across every tenant-facing surface (architecture.md §24.2) — so, like user
 * management, only a super admin may view or change them. There is no
 * "own record" exception here the way there is on UserPolicy: a setting has
 * no owner other than the organisation itself.
 */
final class SettingPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(User $actor, Setting $setting): bool
    {
        return $actor->isSuperAdmin();
    }
}
