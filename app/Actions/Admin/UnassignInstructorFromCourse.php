<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Course;
use App\Models\User;
use App\Services\Audit\AuditLogger;

/**
 * Detach an instructor from a course (FR-ADM-08, FR-INS-11, FR-INS-12).
 *
 * TOUCHES `course_instructor` AND NOTHING ELSE. FR-INS-12 requires that
 * unassigning an instructor never deletes the assessments (or any other
 * content) they authored — those rows live in Track B's own tables and
 * reference `users` directly, not `course_instructor`, so a plain `detach()`
 * on the pivot relation is genuinely safe and there is nothing else here to
 * cascade. Do not "clean up" anything else from this action.
 */
final class UnassignInstructorFromCourse
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Course $course, User $instructor, User $actor): void
    {
        $course->instructors()->detach($instructor->getKey());

        $this->audit->record(
            action: 'course.instructor.unassigned',
            actor: $actor,
            subject: $course,
            description: "Instructor {$instructor->email} unassigned from course \"{$course->title}\" by {$actor->email}.",
        );
    }
}
