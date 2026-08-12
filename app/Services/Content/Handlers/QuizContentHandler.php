<?php

declare(strict_types=1);

namespace App\Services\Content\Handlers;

use App\Enums\LessonType;
use App\Enums\MediaPurpose;
use App\Models\Lesson;
use App\Services\Content\Contracts\LessonContentHandler;

/**
 * Quiz lessons — DELIBERATELY INCOMPLETE UNTIL PHASE 8.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * `phases.md` Phase 5 states plainly: handlers for video, document,
 * presentation, resource and text, with the **quiz handler stubbed until
 * Phase 8**.
 *
 * It is registered rather than omitted for one reason: ContentTypeRegistry
 * throws when asked for an unregistered type, and `LessonType::Quiz` is a
 * legal enum value the database will accept. A missing handler would turn any
 * encounter with a quiz lesson into a hard failure. Registering a handler
 * that refuses gracefully is the safer shape.
 *
 * It is excluded from `selectableTypes()`, so an administrator cannot create
 * one yet — offering a type that cannot be authored would let them build a
 * lesson no student could ever complete.
 *
 * PHASE 8 (Srivathsa, Track B) completes this. The assessment tables belong
 * to his slice and do not exist yet; this class deliberately does not
 * reference them.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class QuizContentHandler implements LessonContentHandler
{
    public function type(): LessonType
    {
        return LessonType::Quiz;
    }

    public function label(): string
    {
        return 'Quiz';
    }

    public function description(): string
    {
        return 'An assessment attached to this lesson. Available from Phase 8.';
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        return [
            'summary' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function mediaPurpose(): ?MediaPurpose
    {
        return null;
    }

    public function requiresMedia(): bool
    {
        return false;
    }

    public function editorView(): string
    {
        return 'admin.lessons.editors.quiz';
    }

    public function playerView(): string
    {
        return 'student.lessons.players.quiz';
    }

    /**
     * Always blocks publication in V1.
     *
     * FAIL-SAFE DIRECTION: a quiz lesson has no assessment behind it until
     * Phase 8, so publishing one would put a dead lesson in front of a paying
     * student. Blocking is visible and fixable; allowing it would be silent.
     *
     * @return list<string>
     */
    public function publishBlockers(Lesson $lesson): array
    {
        return [sprintf(
            'Lesson "%s" is a quiz. Quizzes are built in Phase 8 and cannot be published yet.',
            $lesson->title,
        )];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildMeta(array $input): array
    {
        return [];
    }
}
