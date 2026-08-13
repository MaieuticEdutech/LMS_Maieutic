<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\LessonType;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Content\ContentTypeRegistry;
use App\Services\Content\CourseCounterService;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Add a lesson to a module (FR-CNT-02, FR-CNT-06).
 *
 * The lesson's TYPE decides its behaviour, and that behaviour comes from the
 * registry rather than from a `match` here (FR-CNT-07, P-7). This action
 * never branches on LessonType — it asks the handler. That is what lets a new
 * content type be added without editing this class.
 *
 * `body` is sanitised against an allow-list on save (NFR-SEC-06).
 */
final class CreateLesson
{
    public function __construct(
        private readonly ContentTypeRegistry $registry,
        private readonly AuditLogger $audit,
        private readonly CourseCounterService $counters,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @param  array{
     *     title: string,
     *     type: LessonType,
     *     summary?: string|null,
     *     body?: string|null,
     *     duration_seconds?: int|null,
     *     is_published?: bool,
     * }  $attributes
     */
    public function handle(Module $module, array $attributes, User $actor): Lesson
    {
        $type = $attributes['type'];

        // Fails loudly if the type has no handler — a wiring bug should
        // surface here, not as a blank page in the player.
        $handler = $this->registry->for($type);

        if ($type === LessonType::Quiz) {
            throw new InvalidArgumentException(
                'Quiz lessons cannot be created yet. The assessment engine arrives in Phase 8.',
            );
        }

        $lesson = DB::transaction(function () use ($module, $attributes, $type, $handler): Lesson {
            $lesson = new Lesson;

            $lesson->fill([
                'title' => $attributes['title'],
                'slug' => $this->uniqueSlug($module, $attributes['title']),
                'type' => $type,
                'summary' => $this->sanitizer->plainText($attributes['summary'] ?? null),
                'body' => $this->sanitizer->sanitize($attributes['body'] ?? null),
                'position' => $this->nextPosition($module),
                'duration_seconds' => $attributes['duration_seconds'] ?? null,
                'is_published' => $attributes['is_published'] ?? false,
                // Type-specific attributes, supplied by the handler. This is
                // what lets a new type carry its own data with no schema
                // change.
                'meta' => $handler->buildMeta($attributes) ?: null,
            ]);

            $lesson->module()->associate($module);
            $lesson->save();

            return $lesson;
        });

        $this->counters->refreshModule($module);

        $this->audit->record(
            action: 'lesson.created',
            actor: $actor,
            subject: $lesson,
            description: sprintf(
                'Added %s lesson "%s" to module "%s".',
                $type->value,
                $lesson->title,
                $module->title,
            ),
        );

        return $lesson;
    }

    /**
     * Slugs are unique per module, not globally — the same "Introduction" may
     * legitimately appear in several modules.
     */
    private function uniqueSlug(Module $module, string $title): string
    {
        $base = Str::slug($title) ?: 'lesson';
        $slug = $base;
        $suffix = 1;

        while ($module->lessons()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    /**
     * The next free slot at the end of the module.
     *
     * ZERO-BASED, matching ReorderLessons — see CreateModule::nextPosition()
     * for the full reasoning. The same off-by-one lived in both create
     * actions, which is why fixing only the one that was noticed would have
     * left the inconsistency in place for lessons.
     */
    private function nextPosition(Module $module): int
    {
        $max = $module->lessons()->max('position');

        return $max === null ? 0 : (int) $max + 1;
    }
}
