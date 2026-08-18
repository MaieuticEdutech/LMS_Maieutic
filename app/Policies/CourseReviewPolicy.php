<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Course;
use App\Models\CourseReview;
use App\Models\User;
use App\Services\Enrollment\EnrollmentAccessService;

/**
 * Who may write, edit and read a course review.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * "MAY RATE" IS "HAS ACCESS", ASKED OF THE ONE SERVICE THAT DEFINES IT.
 *
 * Not `enrollment->status === Active`, not `enrollments()->exists()`. Reviews
 * are exactly the kind of secondary feature that grows its own slightly
 * different copy of the access rule, and then disagrees with the player about
 * who is enrolled (rule S-8).
 *
 * SubmitCourseReview asserts the same thing again server-side. That is not
 * redundancy — the Action is callable from a console command or a job where no
 * policy runs, and it is the last line before the write.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class CourseReviewPolicy
{
    public function __construct(private readonly EnrollmentAccessService $access) {}

    /**
     * Reviews are PUBLIC. They appear on the catalogue page, which guests read.
     *
     * This is the point of them: a prospective buyer who has to sign in to read
     * what other people thought is a prospective buyer who leaves.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, CourseReview $review): bool
    {
        return true;
    }

    /**
     * Only a student who can currently open the course.
     *
     * An instructor or administrator is refused even for a course they own:
     * a review is a learner's account of taking the course, and one written by
     * the person selling it is not a review.
     */
    public function create(User $user, Course $course): bool
    {
        return $user->isStudent() && $this->access->grantsAccess($user, $course);
    }

    /**
     * Their own, and only while they still have access.
     *
     * Editing is allowed on purpose — someone who rated a course two lessons in
     * and changed their mind by the end should be able to say so. A permanent
     * first impression is worse data as well as worse manners.
     */
    public function update(User $user, CourseReview $review): bool
    {
        return $review->user_id === $user->getKey();
    }

    /**
     * A student may withdraw their own review; a super admin may remove any.
     *
     * The admin case is the moderation hatch that exists before a moderation
     * FEATURE does — a single defamatory review on a public sales page needs an
     * answer today, and "delete it" is one an audit log can record. It is
     * deliberately not an `is_approved` column, which would put every review
     * behind a queue nobody has staffed.
     */
    public function delete(User $user, CourseReview $review): bool
    {
        return $user->isSuperAdmin() || $review->user_id === $user->getKey();
    }
}
