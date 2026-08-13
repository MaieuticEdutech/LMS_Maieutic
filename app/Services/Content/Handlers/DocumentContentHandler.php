<?php

declare(strict_types=1);

namespace App\Services\Content\Handlers;

use App\Enums\CompletionStrategy;
use App\Enums\LessonType;
use App\Enums\MediaPurpose;

/**
 * PDF notes (FR-FILE-02, FR-STU-09).
 *
 * Served with Content-Disposition: attachment and X-Content-Type-Options:
 * nosniff in Phase 6, so a browser cannot be talked into interpreting the
 * response as anything other than a download.
 */
final class DocumentContentHandler extends AbstractMediaContentHandler
{
    public function type(): LessonType
    {
        return LessonType::Document;
    }

    public function completionStrategy(): CompletionStrategy
    {
        return CompletionStrategy::Manual;
    }

    public function label(): string
    {
        return 'PDF / Notes';
    }

    public function description(): string
    {
        return 'A PDF students can read online or download.';
    }

    public function mediaPurpose(): MediaPurpose
    {
        return MediaPurpose::Document;
    }

    public function editorView(): string
    {
        return 'admin.lessons.editors.document';
    }

    public function playerView(): string
    {
        return 'student.lessons.players.document';
    }
}
