<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\CourseStatus;
use App\Exceptions\CoursePublishException;
use App\Models\Course;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Content\CourseCounterService;
use App\Services\Content\CoursePublishValidator;
use Illuminate\Support\Facades\DB;

/**
 * Publish a course, making it visible and purchasable (FR-CRS-03, FR-CRS-04,
 * AC-17).
 *
 * VALIDATION IS ENFORCED HERE, NOT IN THE UI. The Course Builder shows a live
 * publish checklist from the same CoursePublishValidator, but a checklist is
 * a convenience — this action is the authority. Anything that reaches
 * `handle()` without passing gets an exception, whatever the UI showed.
 *
 * Publishing an empty or half-built course is the failure this guards
 * against: a paying student reaching a course with no lessons in it.
 */
final class PublishCourse
{
    public function __construct(
        private readonly CoursePublishValidator $validator,
        private readonly CourseCounterService $counters,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws CoursePublishException when the course is not ready.
     */
    public function handle(Course $course, User $actor): Course
    {
        $blockers = $this->validator->blockers($course);

        if ($blockers !== []) {
            throw new CoursePublishException($blockers);
        }

        DB::transaction(function () use ($course): void {
            $course->forceFill([
                'status' => CourseStatus::Published,
                // Set once, on first publish. Re-publishing after an
                // unpublish keeps the original date so the catalogue's
                // "newest" ordering does not lie about a course's age.
                'published_at' => $course->published_at ?? now(),
            ])->save();

            // Counters back the catalogue's "12 lessons · 3h 40m", so they
            // must be right at the moment the course becomes visible.
            $this->counters->refreshCourse($course);
        });

        $this->audit->record(
            action: 'course.published',
            actor: $actor,
            subject: $course,
            changes: ['after' => ['status' => CourseStatus::Published->value]],
            description: "Published course \"{$course->title}\".",
        );

        return $course->refresh();
    }
}
