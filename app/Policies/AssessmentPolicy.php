<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Assessment;
use App\Models\User;

/**
 * Authorisation for assessment management (FR-ASMT-02, architecture.md §8.3).
 *
 * Deny-by-default (NFR-SEC-18): every method returns false unless a rule
 * explicitly allows the action.
 *
 * Target shape (architecture.md §8.3): "Admin: all. Instructor: only on
 * assigned courses. Student: none." The instructor branch is DELIBERATELY
 * NOT YET IMPLEMENTED — "assigned courses" means `Course::assignedTo($user)`
 * (architecture.md §8.4), and Track A's `Course`/`Module`/`Lesson` models do
 * not exist on `main` yet. Wiring the instructor branch is out of scope here
 * (planning.md "Do not build ahead") and is picked up once those models land
 * and an assessment can resolve its owning course through `assessable`.
 * Until then, an instructor is denied every action, which is the safe
 * direction to fail in.
 */
final class AssessmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(User $actor, Assessment $assessment): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(User $actor, Assessment $assessment): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(User $actor, Assessment $assessment): bool
    {
        return $actor->isSuperAdmin();
    }

    public function publish(User $actor, Assessment $assessment): bool
    {
        return $actor->isSuperAdmin();
    }
}
