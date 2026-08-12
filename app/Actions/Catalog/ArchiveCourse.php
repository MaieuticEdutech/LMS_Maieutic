<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Retire a course permanently from sale (FR-CRS-03).
 *
 * Distinct from unpublishing: `draft` means "not ready yet or temporarily
 * off sale", `archived` means "this will not be sold again".
 *
 * Like unpublishing, it does not touch enrollments. Students who bought it
 * keep it (FR-CRS-05).
 */
final class ArchiveCourse
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Course $course, User $actor): Course
    {
        $before = $course->status;

        DB::transaction(function () use ($course): void {
            $course->forceFill(['status' => CourseStatus::Archived])->save();
        });

        $this->audit->record(
            action: 'course.archived',
            actor: $actor,
            subject: $course,
            changes: [
                'before' => ['status' => $before->value],
                'after' => ['status' => CourseStatus::Archived->value],
            ],
            description: "Archived course \"{$course->title}\". Existing enrollments are unaffected.",
        );

        return $course->refresh();
    }
}
