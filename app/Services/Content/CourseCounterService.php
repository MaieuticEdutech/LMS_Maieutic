<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Support\Facades\DB;

/**
 * Maintains the denormalised counters on `courses` and `modules`
 * (phases.md Phase 5 "Database work").
 *
 * WHAT THESE ARE, AND WHAT THEY ARE NOT (principle P-8):
 *
 * `courses.modules_count`, `courses.lessons_count`,
 * `courses.total_duration_seconds` and `modules.lessons_count` are a CACHE.
 * They exist so the catalogue can render "12 lessons · 3h 40m" without
 * counting rows for every card on the page.
 *
 * They are NEVER the source of truth. The truth is the rows themselves, and
 * every value here is recomputed from them by `rebuild()`. That is what makes
 * caching safe: if a counter is ever wrong, one command fixes it, and no
 * decision anywhere depends on the cached number being right.
 *
 * Recomputed with SQL aggregates rather than by loading collections and
 * counting in PHP — this runs on every content mutation, and it should cost
 * one query, not one per lesson.
 */
final class CourseCounterService
{
    /**
     * Recalculate every counter for one course.
     *
     * Counts only PUBLISHED content: the numbers are shown to prospective
     * buyers on the catalogue, so counting drafts would advertise lessons
     * that do not exist yet.
     */
    public function refreshCourse(Course $course): void
    {
        $stats = DB::table('modules')
            ->leftJoin('lessons', function ($join): void {
                $join->on('lessons.module_id', '=', 'modules.id')
                    ->whereNull('lessons.deleted_at')
                    ->where('lessons.is_published', true);
            })
            ->where('modules.course_id', $course->getKey())
            ->where('modules.is_published', true)
            ->selectRaw('COUNT(DISTINCT modules.id) AS modules_count')
            ->selectRaw('COUNT(lessons.id) AS lessons_count')
            ->selectRaw('COALESCE(SUM(lessons.duration_seconds), 0) AS total_duration')
            ->first();

        $course->forceFill([
            'modules_count' => (int) ($stats->modules_count ?? 0),
            'lessons_count' => (int) ($stats->lessons_count ?? 0),
            'total_duration_seconds' => (int) ($stats->total_duration ?? 0),
        ])->save();
    }

    /**
     * Recalculate one module's lesson count, then its course's totals.
     */
    public function refreshModule(Module $module): void
    {
        $count = DB::table('lessons')
            ->where('module_id', $module->getKey())
            ->whereNull('deleted_at')
            ->where('is_published', true)
            ->count();

        $module->forceFill(['lessons_count' => $count])->save();

        $course = $module->course;

        if ($course !== null) {
            $this->refreshCourse($course);
        }
    }

    /**
     * Rebuild every counter in the system.
     *
     * Backs the `lms:counters:rebuild` command. Its existence is what proves
     * the counters are derived rather than authoritative — if this could not
     * reproduce them, they would be data, not cache.
     */
    public function rebuildAll(): int
    {
        $touched = 0;

        Module::query()->chunkById(200, function ($modules) use (&$touched): void {
            foreach ($modules as $module) {
                $count = DB::table('lessons')
                    ->where('module_id', $module->getKey())
                    ->whereNull('deleted_at')
                    ->where('is_published', true)
                    ->count();

                $module->forceFill(['lessons_count' => $count])->save();
                $touched++;
            }
        });

        Course::query()->chunkById(200, function ($courses) use (&$touched): void {
            foreach ($courses as $course) {
                $this->refreshCourse($course);
                $touched++;
            }
        });

        return $touched;
    }
}
