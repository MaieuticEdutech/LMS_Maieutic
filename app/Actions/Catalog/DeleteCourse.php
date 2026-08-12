<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Jobs\Media\DeleteOrphanedMedia;
use App\Models\Course;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Soft-delete a course (FR-CRS-06, NFR-DATA-05).
 *
 * SOFT, ALWAYS. A course is referenced by enrollments, orders, payments and
 * progress. Hard-deleting one would either cascade through a student's
 * purchase history or leave dangling references — both worse than keeping a
 * hidden row. `CoursePolicy::forceDelete()` returns false for everyone.
 *
 * Its stored files are cleaned up asynchronously: file deletion is slow, can
 * fail against remote storage, and must not make the admin's request hang or
 * roll back a database transaction that already succeeded.
 *
 * PHASE 6 NOTE: a course with enrollments must not be deletable through the
 * UI (FR-CRS-06). That check cannot be written yet — `enrollments` belongs to
 * Track C and does not exist. It is deliberately NOT stubbed here; it lands
 * with the enrollment engine.
 */
final class DeleteCourse
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Course $course, User $actor): void
    {
        $title = $course->title;
        $id = $course->getKey();

        DB::transaction(function () use ($course): void {
            $course->delete();
        });

        $this->audit->record(
            action: 'course.deleted',
            actor: $actor,
            subject: $course,
            description: "Soft-deleted course \"{$title}\" (#{$id}). Files queued for cleanup.",
        );

        // After commit: a queued cleanup for a rolled-back delete would
        // destroy files belonging to a course that still exists.
        DeleteOrphanedMedia::dispatch(Course::class, $id)->afterCommit();
    }
}
