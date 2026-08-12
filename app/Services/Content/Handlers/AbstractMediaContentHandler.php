<?php

declare(strict_types=1);

namespace App\Services\Content\Handlers;

use App\Enums\MediaPurpose;
use App\Models\Lesson;
use App\Services\Content\Contracts\LessonContentHandler;

/**
 * Shared behaviour for the content types that store an uploaded file:
 * video, document, presentation and resource.
 *
 * They differ only in which MediaPurpose they carry and how they render, so
 * everything else lives here rather than being copied four times. Text and
 * quiz do not extend this — they store no file at all.
 */
abstract class AbstractMediaContentHandler implements LessonContentHandler
{
    abstract public function mediaPurpose(): MediaPurpose;

    public function requiresMedia(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        return [
            'summary' => ['nullable', 'string', 'max:1000'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ];
    }

    /**
     * @return list<string>
     */
    public function publishBlockers(Lesson $lesson): array
    {
        if ($lesson->media()->doesntExist()) {
            return [sprintf(
                'Lesson "%s" is a %s lesson but has no file attached.',
                $lesson->title,
                mb_strtolower($this->label()),
            )];
        }

        return [];
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
