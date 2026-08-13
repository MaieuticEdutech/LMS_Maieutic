<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;
use App\Services\Audit\AuditLogger;

/**
 * Admin edit of a student's own-fillable fields (phases.md Phase 4).
 * `role`/`status` are deliberately out of scope here — {@see SetUserStatus}
 * is the only path that changes status, so the two mutations stay audited
 * as distinct events rather than one edit silently also changing access.
 */
final class UpdateStudent
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null}  $attributes
     */
    public function handle(User $student, array $attributes, User $actor): User
    {
        $before = $student->only(['name', 'email', 'phone']);

        $student->fill([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'phone' => $attributes['phone'] ?? null,
        ])->save();

        $this->audit->record(
            action: 'user.updated',
            actor: $actor,
            subject: $student,
            changes: ['before' => $before, 'after' => $student->only(['name', 'email', 'phone'])],
            description: "Student {$student->email} updated by {$actor->email}.",
        );

        return $student;
    }
}
