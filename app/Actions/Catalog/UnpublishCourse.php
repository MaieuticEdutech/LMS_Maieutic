<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Withdraw a course from the catalogue (FR-CRS-03, FR-CRS-05).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * UNPUBLISHING MUST NOT REVOKE ACCESS FOR EXISTING STUDENTS (FR-CRS-05).
 *
 * They paid for it. Taking a course off sale is a commercial decision; taking
 * it away from someone who bought it is theft.
 *
 * This action therefore touches `courses.status` and NOTHING ELSE. It does
 * not look at enrollments and never will — because access is decided solely
 * by EnrollmentAccessService reading the `enrollments` table, and course
 * status is not part of that decision.
 *
 * The separation is the guarantee. If access ever started consulting course
 * status, this rule would silently break, which is why CourseStatus's own
 * docblock says the same thing.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class UnpublishCourse
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Course $course, User $actor): Course
    {
        $before = $course->status;

        DB::transaction(function () use ($course): void {
            // `published_at` is deliberately NOT cleared: it records when the
            // course first went live, which stays true even while it is off
            // sale.
            $course->forceFill(['status' => CourseStatus::Draft])->save();
        });

        $this->audit->record(
            action: 'course.unpublished',
            actor: $actor,
            subject: $course,
            changes: [
                'before' => ['status' => $before->value],
                'after' => ['status' => CourseStatus::Draft->value],
            ],
            description: "Unpublished course \"{$course->title}\". Existing enrollments are unaffected.",
        );

        return $course->refresh();
    }
}
