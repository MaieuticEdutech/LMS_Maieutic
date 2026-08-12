<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a lesson was marked complete (architecture.md §6.4, §17.2): a video
 * crossing its watch-threshold, a student's manual "mark complete", a
 * passed quiz, or a downloaded resource being acknowledged.
 *
 * Backed values are stored in `lesson_progress.completion_source` and
 * enforced by a database CHECK constraint (ADR-012).
 */
enum CompletionSource: string
{
    case Manual = 'manual';
    case Video = 'video';
    case Assessment = 'assessment';
    case Download = 'download';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $source): string => $source->value, self::cases());
    }
}
