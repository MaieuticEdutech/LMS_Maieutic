<?php

declare(strict_types=1);

namespace App\Services\Content\Contracts;

use App\Enums\LessonType;
use App\Enums\MediaPurpose;
use App\Models\Lesson;

/**
 * One content type's behaviour, in one class (ADR-003, FR-CNT-07, P-7).
 *
 * THE POINT OF THIS INTERFACE:
 *
 * Adding a new content type — SCORM, an embedded video, a live session —
 * must be a NEW CLASS, never a schema change and never an edit to a switch
 * statement scattered through the codebase. Implement this, register it, add
 * two Blade partials, done.
 *
 * That is why there is no `videos`/`notes`/`presentations` table: four
 * near-identical tables would have forced a migration for every new type, and
 * a fifth upload path to secure.
 *
 * A handler answers everything the rest of the system needs to know about a
 * type: how to validate it, what file it accepts, which views render it, and
 * what "completed" means for it. Nothing outside this interface should ever
 * branch on LessonType.
 */
interface LessonContentHandler
{
    /**
     * The type this handler serves. Used as the registry key.
     */
    public function type(): LessonType;

    /**
     * Human label for the Course Builder's type picker.
     */
    public function label(): string;

    /**
     * Short explanation shown beside the label when choosing a type.
     */
    public function description(): string;

    /**
     * Validation rules for this type's editor form, merged on top of the
     * rules every lesson shares (title, position, publication state).
     *
     * @return array<string, mixed>
     */
    public function validationRules(): array;

    /**
     * The kind of file this type stores, or null if it stores none.
     *
     * Drives the size ceiling and extension allow-list in config/lms.php, so
     * limits stay in configuration rather than scattered through handlers.
     */
    public function mediaPurpose(): ?MediaPurpose;

    /**
     * Must a lesson of this type have a file attached before it can be
     * published? (FR-CRS-04)
     *
     * A video lesson with no video would give a paying student an empty page.
     */
    public function requiresMedia(): bool;

    /**
     * Blade view rendering this type's editor in the Course Builder.
     *
     * Returns a view NAME, not a view. The handler declares which template it
     * needs; the phase that owns the UI supplies it. That keeps this class
     * free of presentation and lets backend and frontend land separately.
     */
    public function editorView(): string;

    /**
     * Blade view rendering this type in the student player.
     */
    public function playerView(): string;

    /**
     * Reasons this lesson is not ready to publish. Empty means ready.
     *
     * Returns ALL reasons rather than the first, so an administrator sees
     * everything wrong in one pass instead of discovering the next problem
     * after fixing each one.
     *
     * @return list<string>
     */
    public function publishBlockers(Lesson $lesson): array;

    /**
     * Type-specific attributes to merge into `lessons.meta` (JSONB) on save.
     *
     * This is what lets a new type carry its own data without a schema
     * change — the whole reason `meta` exists.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildMeta(array $input): array;
}
