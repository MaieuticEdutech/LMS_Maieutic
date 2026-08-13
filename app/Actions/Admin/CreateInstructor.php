<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Actions\Identity\SendActivationLink;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\InstructorProfile;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Admin-created instructor account (phases.md Phase 4 Checkpoint 4).
 *
 * Same shape as {@see CreateStudent} — no password, `status =
 * PendingActivation`, handed off to the existing `SendActivationLink` action
 * — but an instructor also gets a linked {@see InstructorProfile} in the same
 * transaction, since `instructor_profile` rows are never created bare
 * (architecture.md §6.4: instructor-specific data is kept out of `users` so
 * the identity table stays role-neutral).
 */
final class CreateInstructor
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SendActivationLink $sendActivationLink,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, headline?: string|null, bio?: string|null}  $attributes
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
            $user->role = UserRole::Instructor;
            $user->status = UserStatus::PendingActivation;

            $user->save();

            $user->instructorProfile()->save(new InstructorProfile([
                'headline' => $attributes['headline'] ?? null,
                'bio' => $attributes['bio'] ?? null,
            ]));

            return $user;
        });

        $this->audit->record(
            action: 'user.created',
            actor: $actor,
            subject: $user,
            description: "Instructor account created for {$user->email} by {$actor->email}.",
        );

        $this->sendActivationLink->handle($user);

        return $user;
    }
}
