<?php

declare(strict_types=1);

namespace App\Services\Progress;

use App\Enums\AssessmentType;
use App\Enums\EnrollmentStatus;
use App\Enums\ProgressStatus;
use App\Events\CourseCompleted;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Derives progress at every level from the one thing that is a fact
 * (architecture.md §17.1, ADR-008).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * THE FACT IS `lesson_progress`. EVERYTHING ELSE IS A CACHE OF IT.
 *
 * `enrollments.progress_percentage` and `completed_lessons_count` are stored
 * so a dashboard listing twenty courses does not count rows twenty times. They
 * are never the source of truth, and `lms:progress:rebuild` exists to prove
 * it: every figure this class produces must be reproducible from the lesson
 * rows alone.
 *
 * If that stopped being true, a drift would be permanent and unrecoverable —
 * there would be nothing to recompute FROM. That is the difference between a
 * cache and data, and it is why the rebuild command is part of the phase
 * rather than a nice-to-have.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * THE DENOMINATOR IS PUBLISHED LESSONS, counted at read time. Publishing a
 * tenth lesson to a course where a student completed nine must move them from
 * 100% to 90% (AC-30) — leaving them at 100% would tell them they had
 * finished a course they had not.
 */
final class ProgressCalculator
{
    /**
     * Recompute and store one enrollment's course figures.
     *
     * Returns the enrollment with fresh values. Safe to call repeatedly: it
     * derives from rows rather than incrementing, so a double call cannot
     * drift the way a counter would.
     */
    /**
     * The figures for an enrollment, WITHOUT writing anything.
     *
     * Split out so a dry run can report drift without causing it. The obvious
     * alternative — recalculate, compare, then restore the old values — would
     * write twice and could fire CourseCompleted, meaning "just checking"
     * emailed a student their certificate.
     *
     * @return array{completed: int, total: int, percentage: int, should_complete: bool}
     */
    public function computeFor(Enrollment $enrollment): array
    {
        $total = $this->publishedLessonCount($enrollment->course_id);
        $completed = $this->completedLessonCount($enrollment);

        return [
            'completed' => $completed,
            'total' => $total,
            // An empty course is 0%, not 100%. Dividing by zero aside, telling
            // a student they have completed a course with no lessons is worse
            // than showing nothing.
            'percentage' => $total > 0 ? (int) floor(($completed / $total) * 100) : 0,
            'should_complete' => $total > 0
                && $completed >= $total
                && $this->finalRequirementMet($enrollment),
        ];
    }

    public function recalculateCourse(Enrollment $enrollment): Enrollment
    {
        $figures = $this->computeFor($enrollment);
        $completed = $figures['completed'];
        $percentage = $figures['percentage'];

        $changes = [
            'completed_lessons_count' => $completed,
            'progress_percentage' => $percentage,
        ];

        $shouldComplete = $figures['should_complete'];

        if ($shouldComplete && $enrollment->completed_at === null) {
            $changes['status'] = EnrollmentStatus::Completed;
            $changes['completed_at'] = now();
        }

        // Falling BACK below 100% — a new lesson was published — returns the
        // enrollment to active. Leaving it `completed` would let a student
        // keep a completion they no longer hold (AC-30).
        if (! $shouldComplete && $enrollment->completed_at !== null) {
            $changes['status'] = EnrollmentStatus::Active;
            $changes['completed_at'] = null;
        }

        $wasComplete = $enrollment->completed_at !== null;

        DB::transaction(static function () use ($enrollment, $changes): void {
            $enrollment->forceFill($changes)->save();
        });

        // Once, on the transition only. A recalculation that finds the course
        // still at 100% must not re-fire the completion email.
        if ($shouldComplete && ! $wasComplete) {
            CourseCompleted::dispatch($enrollment->refresh());
        }

        return $enrollment;
    }

    /**
     * A module's progress, DERIVED and never stored (ADR-008).
     *
     * There is no `modules.progress_percentage`, deliberately: a stored figure
     * at module level would be a third cache to keep in step with the other
     * two, for a number only ever read while its lessons are already loaded.
     *
     * @return array{completed: int, total: int, percentage: int}
     */
    public function moduleProgress(Module $module, Enrollment $enrollment): array
    {
        $lessonIds = [];

        foreach ($module->lessons()->where('is_published', true)->pluck('id') as $id) {
            $lessonIds[] = (int) $id;
        }

        if ($lessonIds === []) {
            return ['completed' => 0, 'total' => 0, 'percentage' => 0];
        }

        $completed = [];

        $rows = DB::table('lesson_progress')
            ->where('enrollment_id', $enrollment->getKey())
            ->whereIn('lesson_id', $lessonIds)
            ->where('status', ProgressStatus::Completed->value)
            ->pluck('lesson_id');

        foreach ($rows as $id) {
            $completed[(int) $id] = true;
        }

        return $this->moduleFigures($lessonIds, $completed);
    }

    /**
     * The same module rule, applied to data the caller has ALREADY loaded.
     *
     * The player renders a sidebar of modules and needs a figure against each.
     * Calling moduleProgress() in that loop would be two queries per module —
     * the exact N+1 CoursePlayerService was built to prevent (NFR-PERF-03). It
     * already holds the published lessons and the whole progress map, so this
     * is the same arithmetic over that data and costs nothing.
     *
     * Both paths end in moduleFigures(), so there is one definition of what a
     * module percentage means rather than two that can drift.
     *
     * @param  iterable<Lesson>  $publishedLessons
     * @param  array<int, LessonProgress>  $progressMap  keyed by lesson id
     * @return array{completed: int, total: int, percentage: int}
     */
    public function moduleProgressFrom(iterable $publishedLessons, array $progressMap): array
    {
        $lessonIds = [];
        $completed = [];

        foreach ($publishedLessons as $lesson) {
            $id = $lesson->getKey();
            $lessonIds[] = $id;

            if (($progressMap[$id] ?? null)?->completed_at !== null) {
                $completed[$id] = true;
            }
        }

        return $this->moduleFigures($lessonIds, $completed);
    }

    /**
     * @param  list<int>  $lessonIds
     * @param  array<int, mixed>  $completed  keyed by completed lesson id
     * @return array{completed: int, total: int, percentage: int}
     */
    private function moduleFigures(array $lessonIds, array $completed): array
    {
        $total = count($lessonIds);

        if ($total === 0) {
            return ['completed' => 0, 'total' => 0, 'percentage' => 0];
        }

        // Intersected rather than counted directly: a progress row can outlive
        // its lesson being unpublished, and counting it would produce "4 of 3
        // complete" in a module header.
        $done = 0;

        foreach ($lessonIds as $id) {
            if (array_key_exists($id, $completed)) {
                $done++;
            }
        }

        return [
            'completed' => $done,
            'total' => $total,
            'percentage' => (int) floor(($done / $total) * 100),
        ];
    }

    /**
     * A student's progress across every course they can still open.
     *
     * Reads the cached per-enrollment figures rather than recounting lesson
     * rows — that is what the cache is for, and a dashboard must stay bounded
     * however many courses someone holds (NFR-PERF-04).
     *
     * @return array{courses: int, completed: int, percentage: int}
     */
    public function studentOverall(User $student): array
    {
        $rows = Enrollment::query()
            ->where('user_id', $student->getKey())
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            // Expiry is evaluated here rather than trusted from the status
            // column, exactly as EnrollmentAccessService and
            // StudentDashboardService do. Without it, an expired enrollment
            // would drag down an overall figure for a course the student
            // cannot open — a number they could not act on or explain.
            // ProgressParityTest asserts this set against the dashboard's.
            ->where(static function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get(['progress_percentage', 'completed_at']);

        $courses = $rows->count();

        if ($courses === 0) {
            return ['courses' => 0, 'completed' => 0, 'percentage' => 0];
        }

        return [
            'courses' => $courses,
            'completed' => $rows->whereNotNull('completed_at')->count(),
            // A mean of course percentages, not of lessons. A student halfway
            // through two courses is 50% overall, regardless of one being ten
            // lessons and the other a hundred — which matches how someone
            // actually thinks about their own progress.
            'percentage' => (int) floor($rows->avg('progress_percentage') ?? 0),
        ];
    }

    /**
     * Recalculate every enrollment in a course.
     *
     * Used by the rebuild command and by the batch job that follows a
     * curriculum change. Chunked: a popular course can hold thousands of
     * enrollments and loading them all would be a memory problem rather than
     * a query problem.
     */
    public function recalculateCourseEnrollments(Course $course): int
    {
        $count = 0;

        Enrollment::query()
            ->where('course_id', $course->getKey())
            ->chunkById(200, function ($enrollments) use (&$count): void {
                foreach ($enrollments as $enrollment) {
                    $this->recalculateCourse($enrollment);
                    $count++;
                }
            });

        return $count;
    }

    private function publishedLessonCount(int $courseId): int
    {
        return DB::table('lessons')
            ->join('modules', 'lessons.module_id', '=', 'modules.id')
            ->where('modules.course_id', $courseId)
            ->where('modules.is_published', true)
            ->where('lessons.is_published', true)
            // `lessons` is soft-deletable and `modules` is not — checked
            // against the migrations rather than assumed. A deleted_at filter
            // on a column that does not exist is not a harmless extra
            // condition, it is a broken query.
            ->whereNull('lessons.deleted_at')
            ->count();
    }

    /**
     * Completed lessons that STILL COUNT.
     *
     * Joined back to the curriculum rather than counting progress rows
     * directly: a row can survive its lesson being unpublished, and counting
     * it would produce "12 of 10 complete" — a figure that destroys trust in
     * every other number on the page.
     */
    private function completedLessonCount(Enrollment $enrollment): int
    {
        return DB::table('lesson_progress')
            ->join('lessons', 'lesson_progress.lesson_id', '=', 'lessons.id')
            ->join('modules', 'lessons.module_id', '=', 'modules.id')
            ->where('lesson_progress.enrollment_id', $enrollment->getKey())
            ->where('lesson_progress.status', ProgressStatus::Completed->value)
            ->where('modules.course_id', $enrollment->course_id)
            ->where('modules.is_published', true)
            ->where('lessons.is_published', true)
            ->whereNull('lessons.deleted_at')
            ->count();
    }

    /**
     * The final-test gate (AC-31, FR-PROG-08).
     *
     * ═════════════════════════════════════════════════════════════════════
     * A COURSE IS NOT COMPLETE UNTIL ITS FINAL TEST IS PASSED.
     *
     * Completing every lesson is necessary and not sufficient where a course
     * sets `requires_final_test`. Treating 100% of lessons as completion
     * would issue an unearned completion — and later a certificate — to a
     * student who never sat the test the course exists to certify.
     *
     * FAIL-SAFE IN BOTH DIRECTIONS, and the two cases differ:
     *
     *   A course that requires a test but has none attached yet returns
     *   FALSE. That leaves students at 100% lessons and not-yet-complete,
     *   which is exactly true and visibly odd — an author who forgot to add
     *   the test will hear about it. Returning true would hide the mistake by
     *   handing out completions.
     *
     *   A course that requires no test is unaffected and completes on lessons
     *   alone.
     * ═════════════════════════════════════════════════════════════════════
     */
    private function finalRequirementMet(Enrollment $enrollment): bool
    {
        $requiresFinalTest = (bool) DB::table('courses')
            ->where('id', $enrollment->course_id)
            ->value('requires_final_test');

        if (! $requiresFinalTest) {
            return true;
        }

        // The final test hangs off the COURSE, not a lesson — that is what
        // distinguishes it from a quiz inside a module (ADR-002).
        $finalTestIds = DB::table('assessments')
            ->where('assessable_type', Course::class)
            ->where('assessable_id', $enrollment->course_id)
            ->where('type', AssessmentType::Test->value)
            ->pluck('id');

        if ($finalTestIds->isEmpty()) {
            // Required but not yet authored. See the docblock: false is the
            // honest answer, and the visible one.
            return false;
        }

        return DB::table('assessment_attempts')
            ->where('enrollment_id', $enrollment->getKey())
            ->whereIn('assessment_id', $finalTestIds)
            ->where('is_passed', true)
            ->exists();
    }
}
