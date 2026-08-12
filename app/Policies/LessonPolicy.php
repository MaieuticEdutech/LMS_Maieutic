<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;

/**
 * Authorisation for lessons (FR-CNT-02, FR-CNT-05, FR-RBAC-05).
 *
 * DELEGATES TO CoursePolicy via the owning module (architecture.md §8.3).
 *
 * THE TWO QUESTIONS, AGAIN — and here the distinction is the product:
 *
 *   view()   — may this user see the lesson TITLE in a curriculum listing?
 *              Yes for a published lesson in a published course: the
 *              catalogue shows the syllabus so a buyer knows what they are
 *              buying (FR-STU-04).
 *
 *   access() — may this user read the lesson BODY, play its video, download
 *              its resources? Requires an active enrollment.
 *
 * THERE IS NO PREVIEW BRANCH (ADR-014, AC-01). Guests see titles and
 * durations, never content. Free-preview lessons are a [V1.1] item; if they
 * are ever added, this is the one method that gains a branch — and it must be
 * a recorded decision, not a quiet edit.
 */
final class LessonPolicy
{
    public function __construct(private readonly CoursePolicy $coursePolicy) {}

    /**
     * See the lesson's TITLE in a curriculum listing. Not its content.
     */
    public function view(?User $user, Lesson $lesson): bool
    {
        $course = $lesson->course();

        if ($course === null) {
            return false;
        }

        $module = $lesson->module;

        // Unpublished at either level hides it from everyone who cannot
        // manage the course (FR-CNT-05).
        if (! $lesson->is_published || $module === null || ! $module->is_published) {
            return $user instanceof User && $this->coursePolicy->manageContent($user, $course);
        }

        return $this->coursePolicy->view($user, $course);
    }

    /**
     * READ THE LESSON'S CONTENT — the paid product.
     *
     * Resolves the owning course, then defers to the single definition of
     * access (rule S-8). Never re-implement with a local enrollment query.
     */
    public function access(User $user, Lesson $lesson): bool
    {
        $course = $lesson->course();
        $module = $lesson->module;

        if ($course === null || $module === null) {
            // Could not resolve the owner. DENY — an unresolvable owner is
            // never treated as "no restriction".
            return false;
        }

        if (! $lesson->is_published || ! $module->is_published) {
            return $this->coursePolicy->manageContent($user, $course);
        }

        return $this->coursePolicy->access($user, $course);
    }

    public function create(User $user, Lesson $lesson): bool
    {
        return $this->manage($user, $lesson);
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $this->manage($user, $lesson);
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $this->manage($user, $lesson);
    }

    public function restore(User $user, Lesson $lesson): bool
    {
        return $this->manage($user, $lesson);
    }

    public function forceDelete(User $user, Lesson $lesson): bool
    {
        return false;
    }

    public function reorder(User $user, Lesson $lesson): bool
    {
        return $this->manage($user, $lesson);
    }

    private function manage(User $user, Lesson $lesson): bool
    {
        $course = $lesson->course();

        return $course !== null && $this->coursePolicy->manageContent($user, $course);
    }
}
