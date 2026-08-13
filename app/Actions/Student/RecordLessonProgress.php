<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Enums\CompletionStrategy;
use App\Enums\ProgressStatus;
use App\Events\LessonCompleted;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Services\Content\ContentTypeRegistry;
use App\Services\Progress\ProgressCalculator;
use App\Services\Progress\ProgressSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Records where a student has reached in a lesson (FR-PROG-01 … FR-PROG-05,
 * AC-18, AC-32).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * THREE PROPERTIES, AND EACH ONE EXISTS BECAUSE THE OBVIOUS VERSION IS WRONG.
 *
 * 1. MONOTONIC STATUS. `completed` never regresses to `in_progress`. A student
 *    who finishes a lesson and re-watches the first minute has still finished
 *    it, and a later position report must not take that away. Only an explicit
 *    un-complete clears it.
 *
 * 2. MAX WATCHED, NOT LAST WATCHED. `video_watched_seconds` takes the maximum
 *    ever reached. Storing the latest value would let scrubbing back to the
 *    start erase evidence of having watched the whole thing — and with it, the
 *    completion the student had earned.
 *
 * 3. THROTTLED WRITES. A video reports position several times a second. One
 *    student on one lesson would be thousands of rows-worth of writes for a
 *    resume point accurate to the same few seconds either way (FR-PROG-02).
 *
 *    The throttle is bypassed whenever something that MATTERS changes —
 *    crossing the completion threshold, a manual tick, a first visit. Throttle
 *    the noise, never the signal.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * IT DOES NOT BRANCH ON LessonType. What "completed" means comes from the
 * handler's CompletionStrategy, so a new content type brings its own rule and
 * this class is untouched (ADR-003, P-7).
 */
final class RecordLessonProgress
{
    /**
     * Columns whose movement is NOISE, for throttling purposes.
     *
     * A playhead two seconds further along is not news. Everything not listed
     * here — a completion, a first visit, an un-complete — changes where the
     * student stands and is written the moment it happens.
     */
    private const ROUTINE_COLUMNS = [
        'video_position_seconds',
        'video_watched_seconds',
        'video_duration_seconds',
    ];

    public function __construct(
        private readonly ContentTypeRegistry $registry,
        private readonly ProgressSettings $settings,
        private readonly ProgressCalculator $calculator,
    ) {}

    /**
     * @param  int|null  $positionSeconds  Playback head, for video lessons.
     * @param  bool|null  $completed  true marks complete, false clears it,
     *                                null leaves completion to the strategy.
     * @param  CompletionStrategy|null  $source  WHO is completing it. Null means
     *                                           the student pressed the button.
     *                                           A passed assessment passes
     *                                           `Assessment` — see below.
     */
    public function handle(
        Enrollment $enrollment,
        Lesson $lesson,
        ?int $positionSeconds = null,
        ?bool $completed = null,
        ?CompletionStrategy $source = null,
    ): LessonProgress {
        $progress = $this->findOrCreate($enrollment, $lesson);
        $wasComplete = $progress->completed_at !== null;

        $changes = $this->resolveChanges($progress, $lesson, $positionSeconds, $completed, $source);

        if (! $this->shouldWrite($progress, $changes)) {
            return $progress;
        }

        if ($changes !== []) {
            $progress->forceFill($changes)->save();
        }

        $this->touchEnrollment($enrollment, $lesson);

        $nowComplete = $progress->completed_at !== null;

        // Only on the transition. A video that keeps reporting after the
        // threshold would otherwise fire this every tick, and each one would
        // recount the whole course.
        if ($nowComplete !== $wasComplete) {
            $this->refreshCourseFigures($enrollment);
        }

        if ($nowComplete && ! $wasComplete) {
            LessonCompleted::dispatch($enrollment, $lesson, $progress);
        }

        return $progress;
    }

    /**
     * Is this call worth a write?
     *
     * ═════════════════════════════════════════════════════════════════════
     * THE THROTTLE APPLIES TO POSITION REPORTS AND NOTHING ELSE.
     *
     * The subtlety that makes this easy to get wrong: a video reports a
     * DIFFERENT position every tick, so a throttle that only skipped
     * *identical* calls would skip almost nothing and the write volume
     * FR-PROG-02 exists to control would be unchanged. The window has to
     * suppress movement, not just repetition.
     *
     * Three things override it, and each has a failure mode behind it:
     *
     *   A FIRST VISIT always lands. A row created a moment ago has an
     *   `updated_at` of now, so the throttle would swallow the very first call
     *   for a lesson and the enrollment's resume pointer would never be set —
     *   "continue learning" would then land on the wrong lesson for anyone who
     *   opened one and left within the window (FR-STU-07, AC-29).
     *
     *   ANY NON-ROUTINE CHANGE lands: crossing the watch threshold, a manual
     *   tick, a passed quiz, an un-complete. A student must never watch the
     *   bar sit still because their completion arrived mid-window.
     *
     *   AN ELAPSED WINDOW lands, even with nothing to say, because being in
     *   this lesson is itself the fact that moves the resume pointer.
     * ═════════════════════════════════════════════════════════════════════
     *
     * @param  array<string, mixed>  $changes
     */
    private function shouldWrite(LessonProgress $progress, array $changes): bool
    {
        if ($progress->wasRecentlyCreated) {
            return true;
        }

        if (array_diff(array_keys($changes), self::ROUTINE_COLUMNS) !== []) {
            return true;
        }

        return $this->throttleElapsed($progress);
    }

    /**
     * Bring the enrollment's cached course figures back in step.
     *
     * ═════════════════════════════════════════════════════════════════════
     * DELIBERATELY INLINE RATHER THAN QUEUED, AND THAT IS A CHANGE OF MIND
     * WORTH RECORDING.
     *
     * The obvious design queues this: a student finishing a lesson should not
     * wait for an aggregate. But the aggregate is two COUNTs over indexed
     * foreign keys, it runs only on a completion TRANSITION — once per lesson,
     * never on a throttled video tick — and the alternative fails in a way the
     * student sees. `queue.default` is `database`; with no worker running, a
     * queued refresh means someone ticks "complete" and watches the bar not
     * move, indefinitely. A number that is wrong on screen is worse than a
     * request that is a few milliseconds longer.
     *
     * The lesson row is already saved by the time this runs, so a failure here
     * leaves the FACT recorded and only the cache stale — which is precisely
     * the situation `lms:progress:rebuild` exists to repair (ADR-008).
     *
     * The curriculum-change path stays queued: that one touches every
     * enrollment in a course and nobody is watching a single figure.
     * ═════════════════════════════════════════════════════════════════════
     */
    private function refreshCourseFigures(Enrollment $enrollment): void
    {
        $this->calculator->recalculateCourse($enrollment);
    }

    /**
     * What should actually change on this row.
     *
     * Returns an empty array when the call carries no new information, which
     * is what lets the throttle skip the write entirely.
     *
     * @return array<string, mixed>
     */
    private function resolveChanges(
        LessonProgress $progress,
        Lesson $lesson,
        ?int $positionSeconds,
        ?bool $completed,
        ?CompletionStrategy $source,
    ): array {
        $changes = [];
        $strategy = $this->registry->for($lesson->type)->completionStrategy();

        if ($progress->first_accessed_at === null) {
            $changes['first_accessed_at'] = now();
        }

        if ($positionSeconds !== null) {
            $position = $this->clampPosition($positionSeconds, $lesson);

            if ($position !== $progress->video_position_seconds) {
                $changes['video_position_seconds'] = $position;
            }

            // MAX, never last. Property 2 in the class docblock.
            if ($position > $progress->video_watched_seconds) {
                $changes['video_watched_seconds'] = $position;
            }

            if ($lesson->duration_seconds !== null && $progress->video_duration_seconds === 0) {
                $changes['video_duration_seconds'] = $lesson->duration_seconds;
            }
        }

        $changes += $this->resolveCompletion($progress, $lesson, $strategy, $changes, $completed, $source);

        // Any real change moves the row out of not-started.
        if ($changes !== [] && $progress->status === ProgressStatus::NotStarted
            && ! array_key_exists('status', $changes)) {
            $changes['status'] = ProgressStatus::InProgress;
        }

        return $changes;
    }

    /**
     * Completion, per the type's strategy.
     *
     * @param  array<string, mixed>  $pending
     * @return array<string, mixed>
     */
    private function resolveCompletion(
        LessonProgress $progress,
        Lesson $lesson,
        CompletionStrategy $strategy,
        array $pending,
        ?bool $completed,
        ?CompletionStrategy $source,
    ): array {
        // An explicit un-complete is the only thing that clears a finished
        // lesson (property 1).
        if ($completed === false) {
            return $progress->completed_at === null ? [] : [
                'status' => ProgressStatus::InProgress,
                'completed_at' => null,
                'completion_source' => null,
            ];
        }

        if ($progress->completed_at !== null) {
            return [];
        }

        if ($completed === true) {
            /*
             * WHO is completing it must match what the type allows.
             *
             * A null source means the student pressed the button, honoured
             * only where the strategy is Manual — offering it on a video
             * would make the threshold pointless, and on a quiz it would let
             * someone skip the assessment entirely.
             *
             * An explicit Assessment source comes from a PASSED attempt
             * (CompleteLessonOnPassedAttempt), and is honoured only on a
             * lesson whose rule actually is the assessment. That pairing is
             * the whole guard: neither path can complete a lesson the other
             * one owns.
             */
            $claimed = $source ?? CompletionStrategy::Manual;

            $permitted = match ($claimed) {
                CompletionStrategy::Manual => $strategy->allowsManualCompletion(),
                CompletionStrategy::Assessment => $strategy === CompletionStrategy::Assessment,
                // Video completes by crossing its threshold, never by being
                // told it did.
                CompletionStrategy::VideoThreshold => false,
            };

            return $permitted ? $this->completedWith($claimed) : [];
        }

        if ($strategy !== CompletionStrategy::VideoThreshold) {
            return [];
        }

        $watched = $pending['video_watched_seconds'] ?? $progress->video_watched_seconds;
        $duration = $lesson->duration_seconds ?? $progress->video_duration_seconds;

        if ($duration <= 0) {
            // No known duration means no percentage to compare against.
            // Refusing to guess is better than completing on the first tick.
            return [];
        }

        $percent = (int) floor(($watched / $duration) * 100);

        return $percent >= $this->settings->videoCompletionThreshold()
            ? $this->completedWith(CompletionStrategy::VideoThreshold)
            : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function completedWith(CompletionStrategy $source): array
    {
        return [
            'status' => ProgressStatus::Completed,
            'completed_at' => now(),
            // Recorded so a row says HOW it completed, not merely that it did.
            // When a threshold changes, rows completed under the old rule can
            // be identified rather than guessed at. Mapped through toSource()
            // because the column is CHECK-constrained to CompletionSource.
            'completion_source' => $source->toSource(),
        ];
    }

    /**
     * Has enough time passed since the last write to allow another?
     */
    private function throttleElapsed(LessonProgress $progress): bool
    {
        $window = $this->settings->writeThrottleSeconds();

        if ($window === 0) {
            return true;
        }

        $last = $progress->updated_at;

        return $last === null || $last->diffInSeconds(now()) >= $window;
    }

    /**
     * The enrollment's own pointer, so "continue learning" resumes correctly
     * (FR-STU-07, AC-29).
     *
     * Updated on every accepted call, including one that changed nothing else:
     * being in this lesson IS the fact worth recording.
     */
    private function touchEnrollment(Enrollment $enrollment, Lesson $lesson): void
    {
        $enrollment->forceFill([
            'last_lesson_id' => $lesson->getKey(),
            'last_accessed_at' => now(),
        ])->save();
    }

    /**
     * Get the row, creating it on first visit.
     *
     * Two overlapping reports for a lesson with no row yet would both insert
     * and one would hit UNIQUE(enrollment_id, lesson_id). The constraint is
     * relied upon rather than raced against — the same shape as
     * GrantEnrollment (AC-32).
     */
    private function findOrCreate(Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        $existing = $this->find($enrollment, $lesson);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($enrollment, $lesson): LessonProgress {
                $progress = new LessonProgress;

                // enrollment_id, lesson_id and user_id are not fillable —
                // ownership is assigned explicitly, never from a payload
                // (NFR-SEC-07).
                $progress->forceFill([
                    'enrollment_id' => $enrollment->getKey(),
                    'lesson_id' => $lesson->getKey(),
                    'user_id' => $enrollment->user_id,
                    'status' => ProgressStatus::InProgress,
                    'first_accessed_at' => now(),
                ])->save();

                return $progress;
            });
        } catch (QueryException $e) {
            if ($e->getCode() !== '23505') {
                throw $e;
            }

            $winner = $this->find($enrollment, $lesson);

            if ($winner === null) {
                throw $e;
            }

            return $winner;
        }
    }

    private function find(Enrollment $enrollment, Lesson $lesson): ?LessonProgress
    {
        return LessonProgress::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->where('lesson_id', $lesson->getKey())
            ->first();
    }

    /**
     * A position the resume point can safely use.
     *
     * Negative becomes zero; past the end becomes the end. A rounding
     * difference between the browser's idea of duration and ours must not
     * leave a student unable to resume because their saved position sits
     * beyond the file.
     */
    private function clampPosition(int $seconds, Lesson $lesson): int
    {
        $seconds = max(0, $seconds);
        $duration = $lesson->duration_seconds;

        if ($duration !== null && $duration > 0) {
            return min($seconds, $duration);
        }

        return $seconds;
    }
}
