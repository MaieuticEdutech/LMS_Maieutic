<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\CourseInstructorRole;
use App\Models\Course;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Attach an instructor to a course (FR-ADM-08, FR-INS-11).
 *
 * IDEMPOTENT BY DESIGN: `course_instructor` carries UNIQUE(course_id,
 * user_id) (Track A's migration comment on that table). Calling this twice
 * for the same pair must never throw and must never create a second row —
 * it simply no-ops on the second call. A pre-check covers the common case;
 * the `QueryException` catch covers the race where two requests both pass
 * the pre-check before either has inserted, which the pre-check alone cannot
 * close (only the database's unique index can).
 *
 * The insert is wrapped in its OWN `DB::transaction()` rather than a bare
 * try/catch around the query. On PostgreSQL a failed statement poisons the
 * enclosing transaction — every later query in it fails with "current
 * transaction is aborted" — until a rollback happens. `DB::transaction()`
 * called while already inside a transaction (the norm here: every Feature
 * test wraps itself in one) uses a SAVEPOINT, so catching the constraint
 * violation and continuing is actually safe rather than corrupting whatever
 * transaction the caller is in.
 */
final class AssignInstructorToCourse
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(
        Course $course,
        User $instructor,
        User $actor,
        CourseInstructorRole $role = CourseInstructorRole::Lead,
    ): void {
        if ($course->isAssignedTo($instructor)) {
            return;
        }

        try {
            DB::transaction(function () use ($course, $instructor, $actor, $role): void {
                $course->instructors()->attach($instructor->getKey(), [
                    'role_in_course' => $role->value,
                    'assigned_by' => $actor->getKey(),
                    'assigned_at' => now(),
                ]);
            });
        } catch (QueryException) {
            // Lost the race to a concurrent assignment of the same pair — the
            // unique index already did its job, so treat this identically to
            // the pre-check having caught it: a clean no-op, not an error.
            return;
        }

        $this->audit->record(
            action: 'course.instructor.assigned',
            actor: $actor,
            subject: $course,
            description: "Instructor {$instructor->email} assigned to course \"{$course->title}\" by {$actor->email}.",
        );
    }
}
