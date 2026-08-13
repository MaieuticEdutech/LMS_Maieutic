<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\InstructorProfile;
use App\Models\User;
use App\Services\Audit\AuditLogger;

/**
 * Admin edit of an instructor's own-fillable User fields AND their
 * InstructorProfile fields (phases.md Phase 4 Checkpoint 4).
 *
 * Audited as ONE `user.updated` entry covering both records — from an
 * auditor's point of view this is a single edit to "the instructor", not two
 * separate mutations, even though it touches two tables. `role`/`status` stay
 * out of scope, exactly as {@see UpdateStudent} — {@see SetUserStatus} is the
 * only path that changes status.
 */
final class UpdateInstructor
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, headline?: string|null, bio?: string|null}  $attributes
     */
    public function handle(User $instructor, array $attributes, User $actor): User
    {
        // Explicit eager load: `preventLazyLoading` (AppServiceProvider) turns
        // an un-eager-loaded `$instructor->instructorProfile` magic-property
        // access into a thrown exception outside production. `loadMissing` is
        // itself not a lazy access — it is the deliberate load that satisfies
        // the guard before anything reads the relation below.
        $instructor->loadMissing('instructorProfile');

        $profile = $instructor->instructorProfile ?? new InstructorProfile;

        $before = array_merge(
            $instructor->only(['name', 'email', 'phone']),
            ['headline' => $profile->headline, 'bio' => $profile->bio],
        );

        $instructor->fill([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'phone' => $attributes['phone'] ?? null,
        ])->save();

        $profile->fill([
            'headline' => $attributes['headline'] ?? null,
            'bio' => $attributes['bio'] ?? null,
        ]);
        $instructor->instructorProfile()->save($profile);

        $after = array_merge(
            $instructor->only(['name', 'email', 'phone']),
            ['headline' => $profile->headline, 'bio' => $profile->bio],
        );

        $this->audit->record(
            action: 'user.updated',
            actor: $actor,
            subject: $instructor,
            changes: ['before' => $before, 'after' => $after],
            description: "Instructor {$instructor->email} updated by {$actor->email}.",
        );

        return $instructor;
    }
}
