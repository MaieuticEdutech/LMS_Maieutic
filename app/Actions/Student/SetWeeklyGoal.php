<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Sets how many minutes a week a student wants to study.
 *
 * A student sets only their OWN goal: the actor and the subject must be the
 * same person, re-asserted here because the column is deliberately not
 * mass-assignable and this is the only door to it.
 */
final class SetWeeklyGoal
{
    public const MIN_MINUTES = 30;

    public const MAX_MINUTES = 2400;

    public const DEFAULT_MINUTES = 300;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException when the actor is not the student.
     * @throws ValidationException when the goal is out of bounds.
     */
    public function handle(User $student, int $minutes, User $actor): User
    {
        if ($actor->isNot($student)) {
            throw new AuthorizationException('You can only set your own weekly goal.');
        }

        if ($minutes < self::MIN_MINUTES || $minutes > self::MAX_MINUTES) {
            throw ValidationException::withMessages([
                'weekly_goal_minutes' => 'Choose a weekly goal between 30 minutes and 40 hours.',
            ]);
        }

        $before = $student->weekly_goal_minutes ?? self::DEFAULT_MINUTES;

        $student->forceFill(['weekly_goal_minutes' => $minutes])->save();

        $this->audit->record(
            action: 'student.weekly_goal.changed',
            actor: $actor,
            subject: $student,
            changes: ['before' => ['weekly_goal_minutes' => $before], 'after' => ['weekly_goal_minutes' => $minutes]],
            description: "Weekly study goal changed from {$before} to {$minutes} minutes by {$actor->email}.",
        );

        return $student;
    }
}
