<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Student\RecordLessonProgress;
use App\Enums\CompletionStrategy;
use App\Events\AttemptGraded;
use App\Models\Lesson;
use App\Services\Content\ContentTypeRegistry;

/**
 * A passed attempt completes the quiz lesson that hosts it (FR-PROG-04,
 * AC-31).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * WHY A LISTENER AND NOT A CALL INSIDE THE GRADING SERVICE.
 *
 * `GradingService` belongs to Track B and answers one question: what did this
 * attempt score. Progress is Track A's. Wiring them through an event keeps
 * the grading path unable to fail because progress tracking did — a student
 * must still get their marks even if a percentage cannot be recalculated.
 *
 * It is the same shape Phase 11 uses for enrollment mail, and for the same
 * reason: the two concerns fail independently.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * ONLY A PASS COMPLETES. A failed attempt records a score and leaves the
 * lesson unfinished, which is the point of having a passing percentage at
 * all. Nothing here needs to un-complete on a later failure: completion is
 * monotonic (RecordLessonProgress), so a student who passed and then retook
 * the quiz out of interest keeps the lesson they earned.
 */
final class CompleteLessonOnPassedAttempt
{
    public function __construct(
        private readonly RecordLessonProgress $progress,
        private readonly ContentTypeRegistry $registry,
    ) {}

    public function handle(AttemptGraded $event): void
    {
        $attempt = $event->attempt;

        if ($attempt->is_passed !== true) {
            return;
        }

        // Loaded explicitly. Model::preventLazyLoading is on outside
        // production, so reading these as properties would throw here rather
        // than in the grading path that dispatched the event — a listener is
        // exactly where a lazy load is least likely to be noticed.
        $attempt->loadMissing(['enrollment', 'assessment']);

        $enrollment = $attempt->enrollment;

        // An assessment can be attempted without an enrollment in some admin
        // and preview paths. There is no progress to record against one.
        if ($enrollment === null) {
            return;
        }

        $lesson = $this->hostLesson($attempt->assessment?->assessable_type, $attempt->assessment?->assessable_id);

        if ($lesson === null) {
            // A course-level final test hangs off the Course, not a Lesson.
            // It gates course completion rather than completing a lesson —
            // ProgressCalculator handles that, and there is nothing to do here.
            return;
        }

        // Guard the strategy rather than assuming. If a lesson's type is ever
        // changed away from quiz, an old attempt arriving late must not
        // complete a lesson whose rule is now manual or a watch threshold.
        if ($this->registry->for($lesson->type)->completionStrategy() !== CompletionStrategy::Assessment) {
            return;
        }

        $this->progress->handle($enrollment, $lesson, completed: true, source: CompletionStrategy::Assessment);
    }

    private function hostLesson(?string $assessableType, ?int $assessableId): ?Lesson
    {
        if ($assessableType !== Lesson::class || $assessableId === null) {
            return null;
        }

        return Lesson::query()->find($assessableId);
    }
}
