<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a lesson of a given type becomes "complete" (FR-PROG-01 … FR-PROG-04).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * WHY THIS IS AN ENUM ON THE HANDLER RATHER THAN A MATCH IN THE ACTION.
 *
 * RecordLessonProgress must not branch on LessonType — that is the rule the
 * whole registry exists to enforce (ADR-003, P-7). It asks the handler what
 * completion means for this type and applies the answer.
 *
 * The alternative — `match ($lesson->type)` inside the progress action —
 * would mean a new content type requires editing progress tracking, which is
 * exactly the coupling the registry was built to avoid.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * The value is also stored on `lesson_progress.completion_source`, so a row
 * records not just THAT it completed but HOW. That matters when a threshold
 * changes: rows completed under the old rule can be identified rather than
 * guessed at.
 */
enum CompletionStrategy: string
{
    /**
     * The student says so.
     *
     * Text, documents, presentations and resources. There is no honest signal
     * that someone has read a PDF — a scroll position proves the page moved,
     * not that anyone read it. Asking is more truthful than inferring, and
     * inventing a fake signal would make every completion figure a lie.
     */
    case Manual = 'manual';

    /**
     * Watched past a configured proportion of the video.
     *
     * The threshold is deliberately below 100%: credits, outros and a student
     * closing the tab a few seconds early are all normal, and demanding the
     * final frame would leave lessons permanently at 99%.
     */
    case VideoThreshold = 'video';

    /**
     * Passed the attached assessment.
     *
     * Live as of Phase 8: GradingService grades an attempt, AttemptGraded
     * fires, and CompleteLessonOnPassedAttempt records the lesson. A FAILED
     * attempt records a score and leaves the lesson unfinished, which is the
     * point of a passing percentage.
     */
    case Assessment = 'assessment';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $strategy): string => $strategy->value, self::cases());
    }

    /**
     * The value RECORDED on the progress row.
     *
     * ═════════════════════════════════════════════════════════════════════
     * THIS IS THE ONE PLACE THE POLICY TYPE CROSSES INTO THE STORED TYPE.
     *
     * `lesson_progress.completion_source` is constrained to CompletionSource
     * by a database CHECK (ADR-012), and CompletionSource carries a case this
     * enum does not — `Download`. The two are related but not the same thing:
     * one is the RULE a content type follows, the other is the FACT recorded
     * against a row.
     *
     * Mapped explicitly rather than passing `->value` across and relying on
     * the two enums happening to share backing strings. They do today. The
     * day one of them changes, an implicit crossover is a CHECK violation in
     * production and this is a compile-time match arm.
     * ═════════════════════════════════════════════════════════════════════
     */
    public function toSource(): CompletionSource
    {
        return match ($this) {
            self::Manual => CompletionSource::Manual,
            self::VideoThreshold => CompletionSource::Video,
            self::Assessment => CompletionSource::Assessment,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Marked complete by the student',
            self::VideoThreshold => 'Watched to the completion threshold',
            self::Assessment => 'Passed the assessment',
        };
    }

    /**
     * Can a student complete this by pressing a button?
     *
     * False for video and assessment: the rule decides, not the student.
     * Offering "mark as complete" on a video would make the threshold
     * pointless, and on a quiz it would let someone skip the assessment
     * entirely.
     */
    public function allowsManualCompletion(): bool
    {
        return $this === self::Manual;
    }

    /**
     * Is the student's completion in their own hands right now?
     *
     * True for manual. False for video and assessment, where a rule decides —
     * the player uses this to show the right control rather than a button
     * that would be ignored.
     */
    public function isStudentDriven(): bool
    {
        return $this === self::Manual;
    }
}
