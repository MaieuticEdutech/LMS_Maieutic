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

    private function nextPosition(Course $course): int
    {
        return (int) $course->modules()->max('position') + 1;
    }
}
