<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use App\Services\Enrollment\EnrollmentAccessService;

/**
 * Authorisation for courses (FR-CRS-01…11, FR-RBAC-04, FR-INS-08).
 *
 * Deny-by-default: every method returns false unless a rule explicitly allows
 * it (NFR-SEC-18).
 *
 * TWO DISTINCT QUESTIONS, DELIBERATELY SEPARATED:
 *
 *   view()   — may this user see the course EXISTS and read its metadata?
 *              True for anyone, for a published course. This is the public
 *              catalogue: title, description, outcomes, curriculum titles,
 *              price (FR-STU-04).
 *
 *   access() — may this user open the course's PROTECTED CONTENT?
 *              Requires an active enrollment. This is the paid product.
 *
 * Conflating them is how a catalogue page leaks a lesson body. `view` is
 * about a shop window; `access` is about the shop.
 *
 * INSTRUCTORS IN V1 (FR-INS-08): an instructor may VIEW an assigned course
 * and its students, but may NOT create, edit or delete courses, modules,
 * lessons or content. They author assessments only. Instructor content
 * authoring is a [V1.1] item.
 */
final class CoursePolicy
{
    public function __construct(private readonly EnrollmentAccessService $access) {}

    /**
     * Browse the catalogue. Public.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * See a course's METADATA — not its content.
     *
     * Published courses are public by design; the catalogue is the shop
     * window. Draft and archived courses are visible only to an admin, or to
     * an instructor assigned to them.
     */
    public function view(?User $user, Course $course): bool
    {
        if ($course->isPublished()) {
            return true;
        }

        if (! $user instanceof User) {
            return false;
        }

        return $user->isSuperAdmin() || $course->isAssignedTo($user);
    }

    /**
     * OPEN THE COURSE'S PROTECTED CONTENT.
     *
     * Delegates to the single definition of access (rule S-8). Never
     * re-implement this check with a local enrollment query.
     */
    public function access(User $user, Course $course): bool
    {
        return $this->access->grantsAccess($user, $course);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Instructors may not edit courses in V1 (FR-INS-08).
     */
    public function update(User $user, Course $course): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->isSuperAdmin();
    }

    public function restore(User $user, Course $course): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Permanent deletion is never permitted through the application.
     *
     * A course with enrollments must not be hard-deletable (FR-CRS-06):
     * students paid for it, and their progress and orders reference it.
     */
    public function forceDelete(User $user, Course $course): bool
    {
        return false;
    }

    /**
     * Publish / unpublish. Admin only, and additionally gated by
     * CoursePublishValidator in Phase 5 — this answers "may you", the
     * validator answers "is it ready".
     */
    public function publish(User $user, Course $course): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Add, edit, reorder or delete modules, lessons and media.
     */
    public function manageContent(User $user, Course $course): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Assign or unassign instructors (FR-ADM-08, FR-INS-11).
     */
    public function manageInstructors(User $user, Course $course): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * See the students enrolled in this course.
     *
     * Admin: any course. Instructor: assigned courses only — this is the
     * boundary AC-03 tests exhaustively.
     */
    public function viewStudents(User $user, Course $course): bool
    {
        return $user->isSuperAdmin() || $course->isAssignedTo($user);
    }

    /**
     * Create and manage assessments on this course.
     *
     * The one authoring capability an instructor DOES have, and only on
     * assigned courses (FR-INS-04).
     */
    public function manageAssessments(User $user, Course $course): bool
    {
        return $user->isSuperAdmin() || $course->isAssignedTo($user);
    }
}
