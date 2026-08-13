<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Events\CourseStructureChanged;
use App\Models\Module;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Content\CourseCounterService;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Support\Facades\DB;

/**
 * Update a module, including publishing or unpublishing it (FR-CNT-05).
 *
 * Publication state changes the course's counters — they count only published
 * content, because the numbers are shown to prospective buyers and must not
 * advertise lessons that are still drafts.
 */
final class UpdateModule
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CourseCounterService $counters,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @param  array{title?: string, description?: string|null, is_published?: bool}  $attributes
     */
    public function handle(Module $module, array $attributes, User $actor): Module
    {
        $before = $module->only(['title', 'is_published']);

        DB::transaction(function () use ($module, $attributes): void {
            $fillable = [];

            if (array_key_exists('title', $attributes)) {
                $fillable['title'] = $attributes['title'];
            }

            if (array_key_exists('description', $attributes)) {
                $fillable['description'] = $this->sanitizer->plainText(
                    $attributes['description'] === null ? null : (string) $attributes['description'],
                );
            }

            if (array_key_exists('is_published', $attributes)) {
                $fillable['is_published'] = (bool) $attributes['is_published'];
            }

            $module->fill($fillable)->save();
        });

        $module->refresh();
        $this->counters->refreshModule($module);

        /*
         * Publishing or unpublishing a module changes the visible curriculum
         * for every enrolled student — and it moves MORE lessons at once than
         * a single lesson toggle does, since an unpublished module hides all
         * of its lessons regardless of their own flags.
         *
         * Same reasoning as UpdateLesson: fired only on a real change, so
         * renaming a module does not queue a recalculation across every
         * enrollment in the course.
         */
        if ($module->course !== null && $before['is_published'] !== $module->is_published) {
            CourseStructureChanged::dispatch(
                $module->course,
                sprintf('Module "%s" was %s.', $module->title, $module->is_published ? 'published' : 'unpublished'),
            );
        }

        $this->audit->record(
            action: 'module.updated',
            actor: $actor,
            subject: $module,
            changes: ['before' => $before, 'after' => $module->only(array_keys($before))],
            description: "Updated module \"{$module->title}\".",
        );

        return $module;
    }
}
