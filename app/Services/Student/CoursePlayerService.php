<?php

declare(strict_types=1);

namespace App\Services\Student;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Services\Progress\ProgressCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Assembles everything the course player needs, in a bounded number of queries.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * THE N+1 THIS EXISTS TO PREVENT.
 *
 * The player renders a curriculum sidebar: every module, every lesson, and a
 * completion tick against each. Written naively — loop the modules, loop the
 * lessons, ask "is this one complete?" — a 60-lesson course costs 60 queries
 * per page view, and it gets worse as courses grow rather than better.
 *
 * So the curriculum is loaded with eager loading (2 queries), progress for the
 * whole enrollment is loaded once and keyed in memory (1 query), and the tick
 * is a hash lookup. Bounded, and it stays bounded at 600 lessons.
 *
 * NFR-PERF-03 asks for this. The test asserts a query COUNT rather than a
 * duration, because a duration passes on a fast laptop and fails in
 * production.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * THIS SERVICE DOES NOT AUTHORISE. Callers check the policy first, which asks
 * EnrollmentAccessService. Everything below assumes access is already
 * established — but note that it still filters to PUBLISHED content, because
 * "may this student be here" and "should this draft lesson be visible" are
 * different questions and only the first one is about access.
 */
final class CoursePlayerService
{
    /**
     * The curriculum a student actually sees.
     *
     * PUBLISHED ONLY, at both levels. An unpublished module hides its lessons
     * even when those lessons are themselves published — a half-finished
     * module appearing in the sidebar because one lesson inside it was ready
     * is exactly the leak an author does not expect.
     *
     * @return Collection<int, Module>
     */
    public function curriculum(Course $course): Collection
    {
        return $course->modules()
            ->where('is_published', true)
            ->orderBy('position')
            ->with([
                'lessons' => static fn (Relation $query) => $query
                    ->where('is_published', true)
                    ->orderBy('position'),
            ])
            ->get();
    }

    /**
     * Progress for every lesson in this enrollment, keyed by lesson id.
     *
     * One query for the whole course. The caller does `$progress[$lesson->id]`
     * rather than asking the database per row.
     *
     * @return array<int, LessonProgress>
     */
    public function progressMap(Enrollment $enrollment): array
    {
        return LessonProgress::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->get()
            ->keyBy('lesson_id')
            ->all();
    }

    /**
     * The flat, ordered list of lessons a student can reach.
     *
     * Next/previous navigation is a position in THIS list, not a database
     * lookup on lesson id. Ordering and publication state are therefore
     * decided once, in one place, and navigation cannot disagree with the
     * sidebar it sits next to.
     *
     * @param  Collection<int, Module>  $curriculum
     * @return list<Lesson>
     */
    public function flatLessons(Collection $curriculum): array
    {
        $lessons = [];

        foreach ($curriculum as $module) {
            foreach ($module->lessons as $lesson) {
                $lessons[] = $lesson;
            }
        }

        return $lessons;
    }

    /**
     * The lesson before and after this one, or null at either end.
     *
     * @param  list<Lesson>  $flat
     * @return array{previous: Lesson|null, next: Lesson|null}
     */
    public function neighbours(array $flat, Lesson $current): array
    {
        $index = null;

        foreach ($flat as $i => $lesson) {
            if ($lesson->getKey() === $current->getKey()) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            // The current lesson is not in the visible list — it was
            // unpublished while the student was reading it. Offering
            // navigation from a lesson that no longer exists in the
            // curriculum would send them somewhere arbitrary.
            return ['previous' => null, 'next' => null];
        }

        return [
            'previous' => $flat[$index - 1] ?? null,
            'next' => $flat[$index + 1] ?? null,
        ];
    }

    /**
     * Where a returning student should land (FR-STU-07).
     *
     * Their last position if it is still visible, otherwise the first lesson.
     * The fallback matters: `last_lesson_id` can point at a lesson that has
     * since been unpublished, and sending someone to a 404 on "continue
     * learning" is worse than sending them to the start.
     *
     * @param  list<Lesson>  $flat
     */
    public function resumeLesson(Enrollment $enrollment, array $flat): ?Lesson
    {
        if ($flat === []) {
            return null;
        }

        $lastId = $enrollment->last_lesson_id;

        if ($lastId !== null) {
            foreach ($flat as $lesson) {
                if ($lesson->getKey() === $lastId) {
                    return $lesson;
                }
            }
        }

        return $flat[0];
    }

    /**
     * The student's enrollment in this course, whatever its state.
     *
     * Unscoped by status deliberately — the caller has already established
     * access through the policy, and this is how the player reaches the row it
     * needs to write progress against. Never use the mere existence of this
     * to decide access (rule S-8).
     */
    public function enrollmentFor(User $student, Course $course): ?Enrollment
    {
        return Enrollment::query()
            ->where('user_id', $student->getKey())
            ->where('course_id', $course->getKey())
            ->first();
    }

    /**
     * Per-module figures for the sidebar, keyed by module id (FR-PROG-06).
     *
     * ZERO ADDITIONAL QUERIES. The curriculum is already loaded with its
     * lessons and the progress map is already in memory, so every figure here
     * is arithmetic over data the page had anyway. Asking ProgressCalculator
     * per module instead would be two queries each — the N+1 this whole
     * service exists to prevent.
     *
     * The rule itself still lives in ProgressCalculator, so the sidebar and a
     * report cannot disagree about what a module percentage means.
     *
     * @param  Collection<int, Module>  $curriculum
     * @param  array<int, LessonProgress>  $progress
     * @return array<int, array{completed: int, total: int, percentage: int}>
     */
    public function moduleProgressMap(Collection $curriculum, array $progress, ProgressCalculator $calculator): array
    {
        $map = [];

        foreach ($curriculum as $module) {
            $map[$module->getKey()] = $calculator->moduleProgressFrom($module->lessons, $progress);
        }

        return $map;
    }

    /**
     * How many of the visible lessons this student has finished.
     *
     * Counted from the same list the sidebar renders, so the figure can never
     * disagree with what is on screen — a "12 of 10 complete" caused by
     * counting progress rows for lessons that have since been unpublished is
     * the kind of bug that destroys trust in every other number on the page.
     *
     * The cached aggregate on the enrollment arrives in Phase 9; this is the
     * honest count until then.
     *
     * @param  list<Lesson>  $flat
     * @param  array<int, LessonProgress>  $progress
     */
    public function completedCount(array $flat, array $progress): int
    {
        $completed = 0;

        foreach ($flat as $lesson) {
            if (($progress[$lesson->getKey()] ?? null)?->completed_at !== null) {
                $completed++;
            }
        }

        return $completed;
    }
}
