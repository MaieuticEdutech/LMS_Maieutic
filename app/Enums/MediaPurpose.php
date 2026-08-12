<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What an uploaded file is FOR (architecture.md §6.4, §15.3).
 *
 * Drives three security-relevant decisions, which is why it is an enum with a
 * database CHECK constraint rather than a free-text label:
 *
 *   1. the upload size ceiling               (config lms.media.max_bytes)
 *   2. the allowed extension list            (config lms.media.allowed_extensions)
 *   3. whether the file is streamed or downloaded
 *
 * A video is STREAMED with Range support and never offered as a download; a
 * resource is DOWNLOADED with Content-Disposition: attachment and nosniff. All
 * of that is enforced server-side in Phase 6 — hiding a download button is not
 * a control (Rule 20).
 */
enum MediaPurpose: string
{
    case Video = 'video';
    case Document = 'document';
    case Presentation = 'presentation';
    case Attachment = 'attachment';
    case Thumbnail = 'thumbnail';

    /**
     * Subtitle / caption track (WebVTT).
     *
     * Reserved. Captions are a [V1.1] item (FR-FILE-16) and no upload path
     * accepts this value in V1 — the case exists because architecture.md §6.4
     * specifies the enum, and because adding it later would require a CHECK
     * constraint migration. A reserved value is not a built feature.
     */
    case Caption = 'caption';

    public function label(): string
    {
        return match ($this) {
            self::Video => 'Video',
            self::Document => 'Document',
            self::Presentation => 'Presentation',
            self::Attachment => 'Attachment',
            self::Thumbnail => 'Thumbnail',
            self::Caption => 'Captions',
        };
    }

    /**
     * Is this file served as a streamed response rather than a download?
     *
     * Only video streams. Everything else is delivered as an attachment.
     */
    public function isStreamed(): bool
    {
        return $this === self::Video;
    }

    /**
     * Does this file live on the PRIVATE content disk?
     *
     * Everything protected does. Thumbnails are the sole exception: they
     * appear on the public catalogue by design, so they belong on the public
     * disk and carry no access restriction (FR-STU-04).
     */
    public function isProtected(): bool
    {
        return $this !== self::Thumbnail;
    }

    /**
     * Config key used to look up this purpose's size ceiling and extension
     * allow-list in config/lms.php.
     */
    public function configKey(): string
    {
        return $this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
