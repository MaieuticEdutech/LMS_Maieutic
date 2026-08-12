<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

/**
 * Authorisation for enrollment RECORD visibility and management
 * (architecture.md §8.3: "view / grant / revoke | Admin only for write;
 * student reads own; instructor reads within assigned course.").
 *
 * Deny-by-default (NFR-SEC-18).
 *
 * THIS IS NOT THE ACCESS GATE. Whether an enrollment grants access to a
 * course's content is `EnrollmentAccessService::grantsAccess()`'s question
 * (§12.2), consumed by `CoursePolicy::access()`. This policy answers a
 * different question — who may look at or manage the enrollment record
 * itself (an admin's enrollment list, a student's "my courses" page, an
 * instructor's cohort view) — and never re-implements the access check
 * (rule S-8).
 *
 * `grant`/`revoke` gate WHO MAY ASK for the mutation; the mutation itself is
 * `GrantEnrollment` (Govind's single-owner action, Phase 6). This policy
 * contains no enrollment-creation or revocation logic.
 *
 * The instructor branch uses `Course::isAssignedTo()` (architecture.md
 * §8.4), the same method `CoursePolicy::viewStudents()` and
 * `manageAssessments()` already use — Track A's `Course` model is on `main`,
 * so this branch is fully implemented here rather than deferred, unlike
 * `AssessmentPolicy`'s instructor branch (see PROGRESS.md).
 */
final class EnrollmentPolicy
{
    public function viewAny(User $actor): bool
    {
        // Each role's listing is scoped at the query layer (an admin's full
        // list, an instructor's assigned-course cohort via
        // Course::assignedTo(), a student's own enrollments) — every
        // authenticated role may attempt the scoped query.
        return true;
    }

    public function view(User $actor, Enrollment $enrollment): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        if ($actor->isInstructor()) {
            return $enrollment->course?->isAssignedTo($actor) ?? false;
        }

        return $actor->isStudent() && $actor->is($enrollment->user);
    }

    /**
     * May this actor ASK for an enrollment to be granted? Admin only —
     * write access, unlike `view`.
     */
    public function grant(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    /**
     * May this actor ASK for an enrollment to be revoked? Admin only.
     */
    public function revoke(User $actor, Enrollment $enrollment): bool
    {
        return $actor->isSuperAdmin();
    }
}
