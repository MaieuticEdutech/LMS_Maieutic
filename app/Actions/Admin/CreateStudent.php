<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Actions\Identity\SendActivationLink;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Admin-created student account (phases.md Phase 4).
 *
 * Same account shape as a purchase-created account (architecture.md §6.4):
 * no password, `status = PendingActivation`. An admin creating a student
 * directly is functionally identical to Phase 12's purchase path reaching
 * the same account state — both hand off to the existing
 * `SendActivationLink` action rather than inventing a second "how does an
 * account get its first password" mechanism.
 */
final class CreateStudent
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SendActivationLink $sendActivationLink,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null}  $attributes
     */
    public function handle(array $attributes, User $actor): User
    {
        $user = DB::transaction(function () use ($attributes): User {
            $user = new User;

            $user->fill([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'phone' => $attributes['phone'] ?? null,
            ]);

            // NOT fillable — privilege fields, assigned explicitly (NFR-SEC-07).
            $user->role = UserRole::Student;
            $user->status = UserStatus::PendingActivation;

            $user->save();

            return $user;
        });

        $this->audit->record(
            action: 'user.created',
            actor: $actor,
            subject: $user,
            description: "Student account created for {$user->email} by {$actor->email}.",
        );

        $this->sendActivationLink->handle($user);

        return $user;
    }
}
