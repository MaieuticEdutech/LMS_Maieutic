<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Lesson;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Content\CourseCounterService;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Support\Facades\DB;

/**
 * Update a lesson, including publishing it (FR-CNT-05, FR-CNT-12).
 *
 * FR-CNT-12: editing a lesson must not break existing progress records for
 * it. That holds because `lesson_progress` references the lesson by id, and
 * nothing here changes the id or the module it belongs to — a lesson is
 * edited in place, never replaced.
 *
 * `type` is NOT updatable. Changing a video lesson into a text lesson would
 * orphan its file and invalidate any progress recorded against watch time.
 * Delete and recreate instead — an explicit, audited pair of actions rather
 * than a silent mutation.
 */
final class UpdateLesson
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CourseCounterService $counters,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @param  array{
     *     title?: string,
     *     summary?: string|null,
     *     body?: string|null,
     *     duration_seconds?: int|null,
     *     is_published?: bool,
     * }  $attributes
     */
    public function handle(Lesson $lesson, array $attributes, User $actor): Lesson
    {
        $before = $lesson->only(['title', 'is_published', 'duration_seconds']);

        DB::transaction(function () use ($lesson, $attributes): void {
            $fillable = [];

            if (array_key_exists('title', $attributes)) {
                $fillable['title'] = $attributes['title'];
            }

            if (array_key_exists('summary', $attributes)) {
                $fillable['summary'] = $this->sanitizer->plainText(
                    $attributes['summary'] === null ? null : (string) $attributes['summary'],
                );
            }

            if (array_key_exists('body', $attributes)) {
                // Sanitised on save, never trusted at render (NFR-SEC-06).
                $fillable['body'] = $this->sanitizer->sanitize(
                    $attributes['body'] === null ? null : (string) $attributes['body'],
                );
            }

            if (array_key_exists('duration_seconds', $attributes)) {
                $fillable['duration_seconds'] = $attributes['duration_seconds'];
            }

            if (array_key_exists('is_published', $attributes)) {
                $fillable['is_published'] = (bool) $attributes['is_published'];
            }

            $lesson->fill($fillable)->save();
        });

        $lesson->refresh();

        $module = $lesson->module;

        if ($module !== null) {
            // Publication state and duration both feed the course totals.
            $this->counters->refreshModule($module);
        }

        $this->audit->record(
            action: 'lesson.updated',
            actor: $actor,
            subject: $lesson,
            changes: ['before' => $before, 'after' => $lesson->only(array_keys($before))],
            description: "Updated lesson \"{$lesson->title}\".",
        );

        return $lesson;
    }
}
