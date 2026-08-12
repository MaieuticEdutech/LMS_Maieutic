<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lesson content type (FR-CNT-06, ADR-003).
 *
 * THIS ENUM IS THE EXTENSION POINT (FR-CNT-07, principle P-7).
 *
 * There is no `videos`, `notes`, `presentations` or `resources` table. A
 * lesson declares its type here, its bytes live in `media_files`, and its
 * type-specific attributes live in `lessons.meta` (JSONB). Adding a new
 * content type — SCORM, embedded video, live session — is:
 *
 *   1. a new case here,
 *   2. one handler class implementing LessonContentHandler,
 *   3. two Blade partials (editor + player).
 *
 * **No schema change.** That is the entire point of the design, and it is
 * why four candidate tables were collapsed into one.
 *
 * Adding a case here requires updating the CHECK constraint on `lessons.type`
 * with a new migration — the database mirrors this enum deliberately
 * (ADR-012), so the two cannot drift.
 */
enum LessonType: string
{
    case Video = 'video';
    case Document = 'document';
    case Presentation = 'presentation';
    case Text = 'text';
    case Resource = 'resource';
    case Quiz = 'quiz';

    public function label(): string
    {
        return match ($this) {
            self::Video => 'Video',
            self::Document => 'PDF / Notes',
            self::Presentation => 'Presentation',
            self::Text => 'Text lesson',
            self::Resource => 'Downloadable resource',
            self::Quiz => 'Quiz',
        };
    }

    /**
     * Does this type require at least one uploaded file to be publishable?
     *
     * Consumed by CoursePublishValidator in Phase 5: a video lesson with no
     * video is not a lesson, and publishing it would give a paying student an
     * empty page.
     */
    public function requiresMedia(): bool
    {
        return match ($this) {
            self::Video, self::Document, self::Presentation, self::Resource => true,
            self::Text, self::Quiz => false,
        };
    }

    /**
     * The MediaPurpose a file attached to this lesson type should carry.
     *
     * Null for types that hold no media of their own.
     */
    public function mediaPurpose(): ?MediaPurpose
    {
        return match ($this) {
            self::Video => MediaPurpose::Video,
            self::Document => MediaPurpose::Document,
            self::Presentation => MediaPurpose::Presentation,
            self::Resource => MediaPurpose::Attachment,
            self::Text, self::Quiz => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
