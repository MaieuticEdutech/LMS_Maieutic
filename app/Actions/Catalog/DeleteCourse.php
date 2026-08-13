<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Exceptions\CourseDeletionException;
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
 * ═════════════════════════════════════════════════════════════════════════
 * A COURSE WITH ENROLLMENTS CANNOT BE DELETED (FR-CRS-06).
 *
 * Even a soft delete. The row would survive, but the course would vanish from
 * every list a student, an instructor and an administrator can see — and a
 * student who paid for it would find their purchase gone with no explanation
 * and no way to raise it.
 *
 * The check counts enrollments of EVERY status, not just active ones. A
 * refunded or expired enrollment is still a commercial record: it is evidence
 * that money moved, and it has to remain reachable for support and for
 * whoever handles a dispute months later.
 *
 * ARCHIVE IS THE ANSWER for a course that should no longer be sold. It hides
 * the course from the catalogue while leaving existing students exactly where
 * they were, which is what an administrator reaching for "delete" almost
 * always actually wants. The error below says so, because an error that
 * refuses without offering the alternative just gets worked around.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class DeleteCourse
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws CourseDeletionException when students are enrolled.
     */
    public function handle(Course $course, User $actor): void
    {
        $this->assertNoEnrollments($course);

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

    /**
     * @throws CourseDeletionException
     */
    private function assertNoEnrollments(Course $course): void
    {
        // exists() rather than count() when only deciding, then count() only
        // for the message — the common case is zero and does not need a total.
        if (! $course->enrollments()->exists()) {
            return;
        }

        $count = $course->enrollments()->count();

        throw new CourseDeletionException(sprintf(
            '"%s" cannot be deleted: %d student%s %s enrolled. Archive it instead — '
            .'that removes it from the catalogue while leaving existing students their access.',
            $course->title,
            $count,
            $count === 1 ? '' : 's',
            $count === 1 ? 'is' : 'are',
        ));
    }
}
