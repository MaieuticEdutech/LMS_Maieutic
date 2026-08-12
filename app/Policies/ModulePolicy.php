<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Module;
use App\Models\User;

/**
 * Authorisation for modules (FR-CNT-01, FR-CNT-05).
 *
 * DELEGATES ENTIRELY TO CoursePolicy (architecture.md §8.3).
 *
 * A module has no independent existence — it belongs to exactly one course,
 * and every question about it is really a question about that course. If this
 * policy grew its own rules, there would be two places to change when course
 * authorisation changes, and one of them would eventually be missed.
 *
 * Deny-by-default: a module whose course cannot be resolved is inaccessible,
 * not unrestricted.
 */
final class ModulePolicy
{
    public function __construct(private readonly CoursePolicy $coursePolicy) {}

    /**
     * See a module in a curriculum listing (titles only, no content).
     */
    public function view(?User $user, Module $module): bool
    {
        $course = $module->course;

        if ($course === null) {
            return false;
        }

        // An unpublished module is invisible to anyone who cannot manage the
        // course, even when the course itself is published (FR-CNT-05).
        if (! $module->is_published) {
            return $user instanceof User && $this->coursePolicy->manageContent($user, $course);
        }

        return $this->coursePolicy->view($user, $course);
    }

    /**
     * Open the module's protected content.
     */
    public function access(User $user, Module $module): bool
    {
        $course = $module->course;

        if ($course === null) {
            return false;
        }

        if (! $module->is_published) {
            return $this->coursePolicy->manageContent($user, $course);
        }

        return $this->coursePolicy->access($user, $course);
    }

    public function create(User $user, Module $module): bool
    {
        return $this->manage($user, $module);
    }

    public function update(User $user, Module $module): bool
    {
        return $this->manage($user, $module);
    }

    public function delete(User $user, Module $module): bool
    {
        return $this->manage($user, $module);
    }

    /**
     * Drag-and-drop reordering (FR-CNT-03).
     */
    public function reorder(User $user, Module $module): bool
    {
        return $this->manage($user, $module);
    }

    private function manage(User $user, Module $module): bool
    {
        $course = $module->course;

        return $course !== null && $this->coursePolicy->manageContent($user, $course);
    }
}
