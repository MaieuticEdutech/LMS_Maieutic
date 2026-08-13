<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;
use App\Services\Audit\AuditLogger;

/**
 * Soft-delete a user (phases.md Phase 4). Shared between student and
 * instructor management. Never a hard delete — NFR-DATA-05, financial and
 * audit history must survive. Authorisation (self/last-Super-Admin guards)
 * is `UserPolicy::delete()`'s job, checked by the caller beforehand.
 */
final class DeleteUser
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $user, User $actor): void
    {
        $email = $user->email;

        $user->delete();

        $this->audit->record(
            action: 'user.deleted',
            actor: $actor,
            subject: $user,
            description: "User {$email} deleted by {$actor->email}.",
        );
    }
}
