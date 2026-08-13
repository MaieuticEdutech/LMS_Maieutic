<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Records where a student has reached in a lesson (FR-PROG-01, AC-18).
 *
 * PHASE 7 SCOPE, DELIBERATELY NARROW: a video position, and a manual "mark as
 * complete". The rules that decide completion automatically — watch-percentage
 * thresholds, assessment gating, course-level aggregation — are Phase 9's, and
 * building them now would be building ahead (Rule 5).
 *
 * What this does have to get right today is the write itself, because Phase 9
 * builds on these rows rather than replacing them.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * WHY THIS IS AN UPSERT AND NOT A create()-OR-update().
 *
 * A video player reports its position every few seconds. Tab it twice, or let
 * a slow request overlap a fast one, and two calls arrive for the same
 * (enrollment, lesson) with neither row existing yet — both check, both find
 * nothing, both insert, one crashes on UNIQUE(enrollment_id, lesson_id).
 *
 * That constraint exists precisely so a student cannot end up with two
 * progress rows for one lesson, which would make "how much have I completed"
 * unanswerable. Here it is relied upon rather than raced against.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * COMPLETION IS NEVER UNDONE BY A LATER POSITION REPORT. A student who
 * finishes a lesson and then re-watches the first minute has still finished
 * it. Only an explicit un-complete clears it.
 */
final class RecordLessonProgress
{
    /**
     * @param  int|null  $positionSeconds  Playback head, for video lessons.
     * @param  bool|null  $completed  true marks complete, false clears it,
     *                                null leaves completion untouched.
     */
    public function handle(
        Enrollment $enrollment,
        Lesson $lesson,
        ?int $positionSeconds = null,
        ?bool $completed = null,
    ): LessonProgress {
        $progress = $this->findOrCreate($enrollment, $lesson);

        $changes = [];

        if ($positionSeconds !== null) {
            // Clamped, not trusted: the position arrives from the browser, and
            // a negative or absurd value would corrupt the resume point. It is
            // capped at the lesson's own duration where one is known.
            $changes['video_position_seconds'] = $this->clampPosition($positionSeconds, $lesson);
        }

        if ($completed === true && $progress->completed_at === null) {
            $changes['completed_at'] = now();
        }

        if ($completed === false) {
            $changes['completed_at'] = null;
        }

        if ($progress->first_accessed_at === null) {
            $changes['first_accessed_at'] = now();
        }

        if ($changes !== []) {
            $progress->forceFill($changes)->save();
        }

        // The enrollment's own pointer, so "continue learning" resumes at the
        // right lesson. Updated on every touch, including one that changed
        // nothing else — being here IS the fact worth recording.
        $enrollment->forceFill([
            'last_lesson_id' => $lesson->getKey(),
            'last_accessed_at' => now(),
        ])->save();

        return $progress;
    }

    /**
     * Get the row, creating it if this is the first visit.
     *
     * The catch is the race described in the class docblock: between the read
     * and the insert, another request may have created it. The unique
     * constraint refuses the duplicate and we re-read the winner, exactly as
     * GrantEnrollment does.
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
                // A unique violation with no row behind it means the
                // constraint fired for a reason we have not understood.
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
     * Negative becomes zero. Past the end becomes the end — a rounding
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
