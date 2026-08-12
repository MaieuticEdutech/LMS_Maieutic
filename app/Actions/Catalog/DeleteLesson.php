<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Jobs\Media\DeleteOrphanedMedia;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Content\CourseCounterService;
use Illuminate\Support\Facades\DB;

/**
 * Delete a lesson and schedule its files for cleanup (FR-CNT-10, FR-FILE-12).
 *
 * Soft delete: a lesson is referenced by progress records, and hard-deleting
 * one would either cascade through a student's history or leave dangling
 * references.
 *
 * File cleanup is queued rather than inline — deleting from remote storage is
 * slow and can fail, and neither should make an admin's request hang or roll
 * back a database change that already succeeded.
 */
final class DeleteLesson
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CourseCounterService $counters,
    ) {}

    public function handle(Lesson $lesson, User $actor): void
    {
        $module = $lesson->module;
        $title = $lesson->title;
        $id = $lesson->getKey();

        DB::transaction(function () use ($lesson): void {
            $lesson->delete();
        });

        if ($module !== null) {
            $this->counters->refreshModule($module);
        }

        $this->audit->record(
            action: 'lesson.deleted',
            actor: $actor,
            subject: $lesson,
            description: "Deleted lesson \"{$title}\" (#{$id}). Files queued for cleanup.",
        );

        DeleteOrphanedMedia::dispatch(Lesson::class, $id)->afterCommit();
    }
}
