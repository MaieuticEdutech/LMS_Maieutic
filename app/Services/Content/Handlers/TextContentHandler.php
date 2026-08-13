<?php

declare(strict_types=1);

namespace App\Services\Content\Handlers;

use App\Enums\CompletionStrategy;
use App\Enums\LessonType;
use App\Enums\MediaPurpose;
use App\Models\Lesson;
use App\Services\Content\Contracts\LessonContentHandler;

/**
 * Rich-text lessons — the only type whose content lives in the database
 * rather than in a file.
 *
 * `lessons.body` holds HTML that has been sanitised against an allow-list ON
 * SAVE (NFR-SEC-06). See HtmlSanitizer for why save-time and not render-time:
 * storing hostile markup and trusting every future read path to escape it is
 * how XSS survives a refactor.
 */
final class TextContentHandler implements LessonContentHandler
{
    public function type(): LessonType
    {
        return LessonType::Text;
    }

    public function completionStrategy(): CompletionStrategy
    {
        return CompletionStrategy::Manual;
    }

    public function label(): string
    {
        return 'Text lesson';
    }

    public function description(): string
    {
        return 'Written content with basic formatting. No file upload needed.';
    }

    /**
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        return [
            'summary' => ['nullable', 'string', 'max:1000'],
            'body' => ['required', 'string', 'max:100000'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ];
    }

    public function mediaPurpose(): ?MediaPurpose
    {
        // Text lessons hold no file of their own. Supplementary attachments
        // are added as separate resource lessons.
        return null;
    }

    public function requiresMedia(): bool
    {
        return false;
    }

    public function editorView(): string
    {
        return 'admin.lessons.editors.text';
    }

    public function playerView(): string
    {
        return 'student.lessons.players.text';
    }

    /**
     * @return list<string>
     */
    public function publishBlockers(Lesson $lesson): array
    {
        if ($lesson->body === null || trim(strip_tags($lesson->body)) === '') {
            return [sprintf('Text lesson "%s" has no content.', $lesson->title)];
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
