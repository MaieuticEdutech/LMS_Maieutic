<?php

declare(strict_types=1);

namespace App\Services\Content\Handlers;

use App\Enums\CompletionStrategy;
use App\Enums\LessonType;
use App\Enums\MediaPurpose;
use App\Models\Lesson;

/**
 * Video lessons (FR-FILE-01, FR-FILE-07, FR-FILE-08).
 *
 * Video is STREAMED, never downloaded. MediaStorageService forces
 * `is_downloadable = false` for this purpose regardless of what a caller
 * asks, and Phase 6 serves it through short-lived signed URLs with HTTP
 * Range support.
 *
 * PD-12: self-hosted MP4 on private storage, no DRM in V1. The limitation is
 * recorded in requirements.md §10 — without DRM an enrolled student can screen
 * record. `meta.provider` exists so a commercial platform (Mux, Bunny,
 * Cloudflare Stream) can be introduced later behind the same interface
 * without a schema change.
 */
final class VideoContentHandler extends AbstractMediaContentHandler
{
    public function type(): LessonType
    {
        return LessonType::Video;
    }

    public function completionStrategy(): CompletionStrategy
    {
        return CompletionStrategy::VideoThreshold;
    }

    public function label(): string
    {
        return 'Video';
    }

    public function description(): string
    {
        return 'An MP4, WebM or MOV video. Streamed to students; never offered as a download.';
    }

    public function mediaPurpose(): MediaPurpose
    {
        return MediaPurpose::Video;
    }

    public function editorView(): string
    {
        return 'admin.lessons.editors.video';
    }

    public function playerView(): string
    {
        return 'student.lessons.players.video';
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        return array_merge(parent::validationRules(), [
            // Duration drives the course total and the player's time display,
            // and Phase 9 uses it for the watch-completion threshold.
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:86400'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildMeta(array $input): array
    {
        return [
            // 'local' today. A commercial provider slots in here later
            // without touching the schema (PD-12).
            'provider' => 'local',
        ];
    }

    /**
     * @return list<string>
     */
    public function publishBlockers(Lesson $lesson): array
    {
        $blockers = parent::publishBlockers($lesson);

        if ($lesson->duration_seconds === null || $lesson->duration_seconds < 1) {
            $blockers[] = sprintf('Video lesson "%s" has no duration set.', $lesson->title);
        }

        return $blockers;
    }
}
