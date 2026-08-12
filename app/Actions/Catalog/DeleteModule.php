<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Jobs\Media\DeleteOrphanedMedia;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Content\CourseCounterService;
use Illuminate\Support\Facades\DB;

/**
 * Delete a module and everything inside it (FR-CNT-10).
 *
 * DESTRUCTIVE AND AUDITED. FR-CNT-10 requires explicit confirmation in the UI
 * (typed confirmation, per FR-ADM-17) — enforced by the interface, recorded
 * here.
 *
 * Lessons are soft-deleted individually rather than left to the database
 * cascade, for two reasons: soft deletes make the removal recoverable, and
 * each lesson's files need queuing for cleanup, which a raw FK cascade would
 * skip entirely — leaving paid content sitting on disk with nothing pointing
 * at it.
 */
final class DeleteModule
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CourseCounterService $counters,
    ) {}

    public function handle(Module $module, User $actor): void
    {
        $course = $module->course;
        $title = $module->title;

        /** @var list<int> $lessonIds */
        $lessonIds = $module->lessons()->pluck('id')->all();

        DB::transaction(function () use ($module): void {
            // Soft-delete children explicitly: recoverable, and it gives each
            // lesson's media a chance to be queued for cleanup.
            $module->lessons()->each(static fn (Lesson $lesson) => $lesson->delete());
            $module->delete();
        });

        if ($course !== null) {
            $this->counters->refreshCourse($course);
        }

        $this->audit->record(
            action: 'module.deleted',
            actor: $actor,
            subject: $module,
            changes: ['before' => ['lesson_count' => count($lessonIds)]],
            description: "Deleted module \"{$title}\" and its ".count($lessonIds).' lesson(s).',
        );

        // After commit — a cleanup queued for a rolled-back delete would
        // destroy files belonging to content that still exists.
        foreach ($lessonIds as $lessonId) {
            DeleteOrphanedMedia::dispatch(Lesson::class, $lessonId)->afterCommit();
        }
    }
}
