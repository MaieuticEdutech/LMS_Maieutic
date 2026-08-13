<?php

declare(strict_types=1);

namespace App\Services\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

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
 * COMPLETE AS OF PHASE 6.
 *
 * Through Phase 3–5 the student branch returned FALSE unconditionally,
 * because the `enrollments` table did not exist yet and the alternative was
 * guessing. That deny was deliberate, and the direction mattered: a forgotten
 * Phase 6 would have shown students 403 on content they owned — visible,
 * annoying, reported within minutes. A default of true would have shown paid
 * content to strangers, silently.
 *
 * The student branch is now real (FR-ENR-07). The signature, the callers and
 * the admin/instructor branches are unchanged, exactly as planned.
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

        return $this->hasLiveEnrollment($user, $course);
    }

    /**
     * The student rule, and the whole of it (FR-ENR-07).
     *
     * GRANTS ACCESS: `active` — currently enrolled.
     *                `completed` — finished the course. Access is not
     *                withdrawn on completion; a student may revisit material
     *                they have paid for, and revoking at 100% progress would
     *                be a bizarre reward for finishing.
     *
     * DENIES:        `suspended` — access paused by an administrator.
     *                `expired` — the access window closed.
     *                `refunded` — the money went back, so the access goes
     *                with it. This is the one that would be quietly
     *                catastrophic to get wrong.
     *
     * Expiry is evaluated at read time rather than trusted from the status
     * column. The ExpireEnrollments command flips `active` to `expired` on a
     * schedule, but a schedule can be late, stopped, or not yet deployed. If
     * access depended on that job having run, a paused scheduler would silently
     * extend everyone's access — the failure would be invisible and would look
     * exactly like normal operation.
     *
     * So the date is the authority and the status column is a cache of it. The
     * two can disagree for at most one scheduler interval, and when they do,
     * this method is right.
     */
    private function hasLiveEnrollment(User $user, Course $course): bool
    {
        return Enrollment::query()
            ->where('user_id', $user->getKey())
            ->where('course_id', $course->getKey())
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->where(static function (Builder $query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
}
