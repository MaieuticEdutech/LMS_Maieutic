<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\User;

/**
 * Authorisation for assessment attempts (architecture.md §8.3: "start /
 * answer / submit / review | Owner only for write; instructor/admin read
 * within scope.").
 *
 * Deny-by-default (NFR-SEC-18).
 *
 * CALLING `start()`: it takes an `Assessment`, not an `AssessmentAttempt`
 * (there is no attempt yet when starting one). `$user->can('start',
 * $assessment)` resolves the WRONG policy — Laravel picks a policy by the
 * argument's class, and `Assessment`'s registered policy is
 * `AssessmentPolicy`, which has no `start` method. Call it as
 * `$user->can('start', [AssessmentAttempt::class, $assessment])` instead —
 * the array form forces resolution to this policy while still passing
 * `$assessment` through as the argument.
 *
 * `start()` is intentionally coarse — role and publish state only.
 * FR-ASMT-09's fuller conditions (enrolled, attempt count below the limit)
 * are NOT checked here: they require resolving which course the assessment
 * protects (`assessable` is polymorphic — Lesson, Module or Course) and
 * then consulting `EnrollmentAccessService`, which is `StartAttempt`'s job
 * in Phase 8, re-asserting domain invariants the way every Action in this
 * codebase does (architecture.md §8.2). The policy answers "is this even
 * the right kind of actor"; the Action answers "is this specific attempt
 * allowed right now."
 *
 * THE INSTRUCTOR/ADMIN "READ WITHIN SCOPE" BRANCH IS COMPLETED IN PHASE 10.
 * Resolved through `$attempt->assessment->resolveCourse()->isAssignedTo()` —
 * the same polymorphic-chain-then-ask pattern `AssessmentPolicy` now uses.
 * FR-ASMT-17: instructors may view attempt lists and per-question
 * breakdowns for their permitted courses only.
 */
final class AttemptPolicy
{
    public function start(User $actor, Assessment $assessment): bool
    {
        return $actor->isStudent() && $assessment->is_published;
    }

    public function answer(User $actor, AssessmentAttempt $attempt): bool
    {
        return $this->isOwner($actor, $attempt);
    }

    public function submit(User $actor, AssessmentAttempt $attempt): bool
    {
        return $this->isOwner($actor, $attempt);
    }

    public function review(User $actor, AssessmentAttempt $attempt): bool
    {
        if ($this->isOwner($actor, $attempt)) {
            return true;
        }

        if ($actor->isSuperAdmin()) {
            return true;
        }

        if (! $actor->isInstructor()) {
            return false;
        }

        // DENY-SAFE: an unresolvable course is denied, never "no
        // restriction" — same reasoning as AssessmentPolicy's equivalent
        // guard.
        $course = $attempt->assessment?->resolveCourse();

        return $course !== null && $course->isAssignedTo($actor);
    }

    private function isOwner(User $actor, AssessmentAttempt $attempt): bool
    {
        return $actor->isStudent() && $actor->is($attempt->user);
    }
}
