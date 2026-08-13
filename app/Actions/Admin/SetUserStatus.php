<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Audit\AuditLogger;

/**
 * Activate, deactivate or suspend an account (phases.md Phase 4). Shared
 * between student and instructor management — this is the ONE place a
 * status transition happens, so "deactivating a user immediately prevents
 * their login" (Phase 4 testing requirement) only has one code path to hold
 * true.
 *
 * Authorisation (last-Super-Admin guard, self-modification guard) is
 * `UserPolicy::changeStatus()`'s job, checked by the caller before this
 * runs (architecture.md §8.2) — this action re-asserts nothing about WHO
 * may call it, only performs the already-authorised change.
 */
final class SetUserStatus
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $user, UserStatus $status, User $actor): User
    {
        $before = $user->status;

        $user->forceFill(['status' => $status])->save();

        $this->audit->record(
            action: 'user.status.changed',
            actor: $actor,
            subject: $user,
            changes: ['before' => ['status' => $before->value], 'after' => ['status' => $status->value]],
            description: "Status changed from {$before->label()} to {$status->label()} for {$user->email} by {$actor->email}.",
        );

        return $user;
    }
}
