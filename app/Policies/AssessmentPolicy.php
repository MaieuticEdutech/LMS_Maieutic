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
 * assigned courses. Student: none."
 *
 * COMPLETED IN PHASE 10. The instructor branch was deliberately deferred
 * through Phases 5-9 — "assigned courses" means `Course::assignedTo($user)`
 * / `Course::isAssignedTo()`, and those didn't exist until Track A's
 * catalogue models landed. Resolved here via `Assessment::resolveCourse()`,
 * the same "walk the polymorphic assessable chain, then ask" pattern
 * MediaFilePolicy already uses.
 *
 * `create()` has no Assessment to resolve a course from — there is no row
 * yet. Same shape as CoursePolicy::create() being coarse: any instructor may
 * attempt to create, and the calling code (an Instructor-scoped Livewire
 * component) is responsible for checking `$course->isAssignedTo($actor)`
 * against the SPECIFIC lesson/module/course being attached to before ever
 * invoking CreateAssessment. This mirrors CoursesTable/CourseBuilder's own
 * split between "may this role act" (policy) and "is this specific record
 * in scope" (the component, backed by Course::assignedTo() everywhere it
 * queries).
 */
final class AssessmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->isInstructor();
    }

    public function view(User $actor, Assessment $assessment): bool
    {
        return $actor->isSuperAdmin() || $this->isAssignedInstructor($actor, $assessment);
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->isInstructor();
    }

    public function update(User $actor, Assessment $assessment): bool
    {
        return $actor->isSuperAdmin() || $this->isAssignedInstructor($actor, $assessment);
    }

    public function delete(User $actor, Assessment $assessment): bool
    {
        return $actor->isSuperAdmin() || $this->isAssignedInstructor($actor, $assessment);
    }

    public function publish(User $actor, Assessment $assessment): bool
    {
        return $actor->isSuperAdmin() || $this->isAssignedInstructor($actor, $assessment);
    }

    /**
     * DENY-SAFE: an assessment whose course cannot be resolved (an
     * unattached or orphaned assessable) is denied to an instructor, never
     * treated as "no restriction" — same reasoning as
     * MediaFilePolicy::manage() and Assessment::resolveCourse()'s own
     * docblock.
     */
    private function isAssignedInstructor(User $actor, Assessment $assessment): bool
    {
        if (! $actor->isInstructor()) {
            return false;
        }

        $course = $assessment->resolveCourse();

        return $course !== null && $course->isAssignedTo($actor);
    }
}
