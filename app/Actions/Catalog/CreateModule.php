<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Content\CourseCounterService;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Support\Facades\DB;

/**
 * Add a module to a course (FR-CNT-01).
 *
 * Created unpublished: a module appears to students only once it is
 * deliberately published, so building a course in a live catalogue does not
 * leak half-finished sections (FR-CNT-05).
 *
 * Position is assigned as "last", inside the transaction, so two admins
 * adding a module at the same time cannot both claim the same slot.
 */
final class CreateModule
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CourseCounterService $counters,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @param  array{title: string, description?: string|null, is_published?: bool}  $attributes
     */
    public function handle(Course $course, array $attributes, User $actor): Module
    {
        $module = DB::transaction(function () use ($course, $attributes): Module {
            $module = new Module;

            $module->fill([
                'title' => $attributes['title'],
                'description' => $this->sanitizer->plainText($attributes['description'] ?? null),
                'position' => $this->nextPosition($course),
                'is_published' => $attributes['is_published'] ?? false,
            ]);

            $module->course()->associate($course);
            $module->save();

            return $module;
        });

        $this->counters->refreshCourse($course);

        $this->audit->record(
            action: 'module.created',
            actor: $actor,
            subject: $module,
            description: "Added module \"{$module->title}\" to \"{$course->title}\".",
        );

        return $module;
    }

    /**
     * The next free slot at the end of the course.
     *
     * ZERO-BASED, matching ReorderModules, which writes positions from the
     * index of the submitted array. The two disagreed until now: a fresh
     * course numbered its modules 1, 2, 3, and the first drag renumbered them
     * 0, 1, 2.
     *
     * Ordering was correct either way — it is `ORDER BY position` — so nothing
     * was visibly broken. It becomes a real bug the moment anything treats
     * position 0 as "first": a move-to-top control, an import, or a test
     * asserting a known layout. Cheaper to align while it is still harmless.
     *
     * max() returns null on an empty course, so the first module lands at 0.
     */
    private function nextPosition(Course $course): int
    {
        $max = $course->modules()->max('position');

        return $max === null ? 0 : (int) $max + 1;
    }
}
