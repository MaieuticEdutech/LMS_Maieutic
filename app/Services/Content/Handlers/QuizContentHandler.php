<?php

declare(strict_types=1);

namespace App\Services\Content\Handlers;

use App\Enums\LessonType;
use App\Enums\MediaPurpose;
use App\Models\Assessment;
use App\Models\Lesson;
use App\Services\Content\Contracts\LessonContentHandler;

/**
 * Quiz lessons — COMPLETED IN PHASE 8.
 *
 * A quiz lesson carries no content of its own: its "content" is the
 * {@see Assessment} attached to it via the same polymorphic `assessable`
 * relation Module and Course final tests use (architecture.md §10.1). This
 * handler never reaches for `assessments`/`questions` tables directly — it
 * asks `Assessment::query()` the same way `Assessment::resolveCourse()` asks
 * the reverse question, keeping the assessment engine's own tables the
 * single place that schema is known.
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
        return 'An assessment attached to this lesson — questions, marks, time limit and grading.';
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
     * Blocks publication until the lesson has an assessment attached, and
     * that assessment is itself published (which already requires at least
     * one question and non-zero total marks — AssessmentPublishValidator).
     * A quiz lesson published with no working assessment behind it would
     * give a paying student an empty page, same reasoning as every other
     * content type's media requirement.
     *
     * @return list<string>
     */
    public function publishBlockers(Lesson $lesson): array
    {
        $assessment = $this->assessmentFor($lesson);

        if ($assessment === null) {
            return [sprintf('Lesson "%s" is a quiz with no assessment attached yet.', $lesson->title)];
        }

        if (! $assessment->is_published) {
            return [sprintf('Lesson "%s"\'s assessment is not published.', $lesson->title)];
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

    private function assessmentFor(Lesson $lesson): ?Assessment
    {
        return Assessment::query()
            ->where('assessable_type', Lesson::class)
            ->where('assessable_id', $lesson->getKey())
            ->first();
    }
}
