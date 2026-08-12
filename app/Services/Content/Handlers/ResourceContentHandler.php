<?php

declare(strict_types=1);

namespace App\Services\Content\Handlers;

use App\Enums\LessonType;
use App\Enums\MediaPurpose;

/**
 * Generic downloadable resources — worksheets, datasets, code samples
 * (FR-FILE-02).
 *
 * The widest allow-list of any type, which makes it the one most worth
 * watching: `zip`, `docx`, `xlsx` and friends are permitted, but every upload
 * still passes the same magic-byte content sniff, so a renamed executable is
 * rejected here exactly as it would be anywhere else (AC-21).
 */
final class ResourceContentHandler extends AbstractMediaContentHandler
{
    public function type(): LessonType
    {
        return LessonType::Resource;
    }

    public function label(): string
    {
        return 'Downloadable resource';
    }

    public function description(): string
    {
        return 'A worksheet, dataset, archive or other file students download.';
    }

    public function mediaPurpose(): MediaPurpose
    {
        return MediaPurpose::Attachment;
    }

    public function editorView(): string
    {
        return 'admin.lessons.editors.resource';
    }

    public function playerView(): string
    {
        return 'student.lessons.players.resource';
    }
}
