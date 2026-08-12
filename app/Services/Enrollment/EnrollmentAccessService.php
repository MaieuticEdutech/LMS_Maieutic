<?php

declare(strict_types=1);

namespace App\Services\Enrollment;

use App\Models\Course;
use App\Models\User;

/**
 * THE SINGLE DEFINITION OF "HAS ACCESS TO THIS COURSE" (architecture.md §12.2).
 *
 * ─────────────────────────────────────────────────────────────────────────
 * SINGLE-OWNER COMPONENT — Track A (Govind). planning.md §21.3.
 * Do not edit from another track. Consume it; if it needs a change, ask.
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Every policy that asks "may this user reach this content?" delegates here —
 * CoursePolicy, ModulePolicy, LessonPolicy, MediaFilePolicy, and from Phase 8
 * AssessmentPolicy. Rule S-8: there is exactly one definition of access in
 * this system, and no caller may re-implement it with a local enrollment
 * query, however small.
 *
 * That is not tidiness. An access rule implemented in five places is an
 * access rule that is wrong in at least one of them, and the wrong one is
 * discovered by a student reaching content they did not pay for.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * PHASE 3 STATE: INCOMPLETE, AND DELIBERATELY FAIL-SAFE.
 *
 * The student branch requires the `enrollments` table, which belongs to
 * Track C (Shashank) and does not exist yet. Rather than guess, stub or
 * work around it, the student branch currently returns FALSE — deny.
 *
 * The failure direction matters. If Phase 6 were somehow forgotten, the
 * consequence is students seeing 403 on content they own: visible, annoying,
 * immediately reported, harmless. Had it defaulted to true, the consequence
 * would be unenrolled strangers reading paid content — silent, invisible,
 * and exactly the failure this project exists to prevent.
 *
 * PHASE 6 completes this: an enrollment with status active|completed that has
 * not expired grants access (FR-ENR-07). Nothing else about this class
 * changes — the signature, the callers and the admin/instructor branches are
 * already final.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class EnrollmentAccessService
{
    /**
     * Request-scoped memoisation.
     *
     * A single page render asks this question many times — once per lesson in
     * the curriculum sidebar, once per media file in the player. Without
     * memoisation that is a query per lesson (NFR-PERF-03).
     *
     * Keyed by user and course id. Never cached across requests: an
     * administrator revoking an enrollment must take effect immediately
     * (FR-ENR-08), not after a cache expires.
     *
     * @var array<string, bool>
     */
    private array $memo = [];

    /**
     * May this user reach this course's protected content?
     *
     * Answers for every role:
     *   super_admin — always. They administer the platform.
     *   instructor  — only if ASSIGNED to this course (FR-RBAC-04, AC-03).
     *                 Being an instructor grants nothing by itself.
     *   student     — only with an active enrollment. PHASE 6.
     */
    public function grantsAccess(User $user, Course $course): bool
    {
        $key = $user->getKey().':'.$course->getKey();

        return $this->memo[$key] ??= $this->resolve($user, $course);
    }

    /**
     * Clear the memo — for tests, and for long-running processes such as
     * queue workers where one instance may serve several users.
     */
    public function flush(): void
    {
        $this->memo = [];
    }

    private function resolve(User $user, Course $course): bool
    {
        // An account that cannot authenticate cannot hold access, whatever
        // else is true of it. Checked first so a suspended student with a
        // valid enrollment is still refused.
        if (! $user->canAuthenticate()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isInstructor()) {
            return $course->isAssignedTo($user);
        }

        // PHASE 6: replace with the enrollment lookup once Track C's
        // `enrollments` table exists. Denies until then — see class docblock
        // for why that direction is the safe one.
        return false;
    }
}
