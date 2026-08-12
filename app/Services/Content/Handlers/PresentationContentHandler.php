<?php

declare(strict_types=1);

namespace App\Services\Content\Handlers;

use App\Enums\LessonType;
use App\Enums\MediaPurpose;

/**
 * PPT / PPTX / ODP presentations (FR-FILE-02, FR-FILE-14).
 *
 * FR-FILE-14 is explicit that these are DOWNLOADABLE in V1 and that
 * in-browser preview is not required. Rendering Office formats in a browser
 * means either a third-party viewer service (sending customer content to
 * someone else) or a conversion pipeline — neither is justified for V1.
 */
final class PresentationContentHandler extends AbstractMediaContentHandler
{
    public function type(): LessonType
    {
        return LessonType::Presentation;
    }

    public function label(): string
    {
        return 'Presentation';
    }

    public function description(): string
    {
        return 'A PPT, PPTX or ODP deck. Students download it; no in-browser preview in V1.';
    }

    public function mediaPurpose(): MediaPurpose
    {
        return MediaPurpose::Presentation;
    }

    public function editorView(): string
    {
        return 'admin.lessons.editors.presentation';
    }

    public function playerView(): string
    {
        return 'student.lessons.players.presentation';
    }
}
