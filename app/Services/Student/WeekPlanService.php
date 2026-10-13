<?php

declare(strict_types=1);

namespace App\Services\Student;

use App\Actions\Student\SetWeeklyGoal;
use App\Enums\ProgressStatus;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * "Your week, planned": computed from real progress, nothing stored.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * THE WEEK IS MONDAY TO SUNDAY IN ASIA/KOLKATA, NOT IN THE APP TIMEZONE.
 * Timestamps are stored in UTC, so a lesson finished at 00:30 IST on Monday
 * is still Sunday 19:00 UTC. Using UTC would credit it to the previous week.
 *
 * DONE     = lesson_progress rows completed inside this IST week.
 * UPCOMING = the next unfinished lessons, in course / module / lesson order,
 * across courses the student can reach NOW. The list comes from
 * StudentDashboardService::activeEnrollments(), which applies the same live-
 * enrolment rule as EnrollmentAccessService (rule S-8) as a single query. The
 * planner does not ask the access service again per course: that is the same
 * question answered a second time, once per course, on the dashboard's hot
 * path (NFR-PERF-04).
 *
 * Planning fills today and the days after it until the remaining goal is
 * covered, and then stops: the last lesson may overshoot the goal by part of
 * itself, but no further lesson is added.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class WeekPlanService
{
    public const TIMEZONE = 'Asia/Kolkata';

    /** A lesson with no recorded duration still takes some time. */
    private const MIN_LESSON_MINUTES = 5;

    /** Used for a quiz that has no time limit. */
    private const DEFAULT_QUIZ_MINUTES = 10;

    public function __construct(
        private readonly StudentDashboardService $dashboard,
        private readonly CoursePlayerService $player,
    ) {}

    public function forStudent(User $student, ?CarbonImmutable $now = null): WeekPlan
    {
        $now = ($now ?? CarbonImmutable::now())->setTimezone(self::TIMEZONE);
        $weekStart = $now->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
        $weekEnd = $weekStart->addDays(7);
        $today = $now->startOfDay();

        $goal = $student->weekly_goal_minutes ?? SetWeeklyGoal::DEFAULT_MINUTES;

        // Courses this student can open right now.
        $courses = [];
        foreach ($this->dashboard->activeEnrollments($student) as $enrollment) {
            $course = $enrollment->course;
            if ($course !== null) {
                $courses[$course->getKey()] = $course;
            }
        }

        if ($courses === []) {
            return new WeekPlan($this->emptyDays($weekStart, $today), 0, $goal, false);
        }

        // Curriculum per course, in player order. One query for every course
        // at once: the dashboard must not cost a query per enrolled course
        // (NFR-PERF-04).
        $curricula = $this->curricula(array_keys($courses));

        /** @var array<int, list<Lesson>> $lessonsByCourse */
        $lessonsByCourse = [];
        /** @var array<int, array{0: Course, 1: Lesson}> $lessonIndex */
        $lessonIndex = [];
        $lessonIds = [];
        foreach ($courses as $courseId => $course) {
            $flat = $this->player->flatLessons($curricula[$courseId] ?? new Collection);
            $lessonsByCourse[$courseId] = $flat;
            foreach ($flat as $lesson) {
                $lessonIds[] = $lesson->getKey();
                $lessonIndex[$lesson->getKey()] = [$course, $lesson];
            }
        }

        $quizzes = $this->quizzesByLesson($lessonIds);

        // Every completed lesson of this student, for "unfinished" and "done".
        $completed = LessonProgress::query()
            ->where('user_id', $student->getKey())
            ->where('status', ProgressStatus::Completed)
            ->whereIn('lesson_id', $lessonIds)
            ->get();

        $completedIds = $completed->pluck('lesson_id')->flip()->all();

        /** @var array<string, list<WeekPlanBlock>> $doneByDay */
        $doneByDay = [];
        $doneMinutes = 0;
        foreach ($completed as $row) {
            $at = $row->completed_at?->setTimezone(self::TIMEZONE);
            if ($at === null || $at < $weekStart || $at >= $weekEnd || ! isset($lessonIndex[$row->lesson_id])) {
                continue;
            }

            [$course, $lesson] = $lessonIndex[$row->lesson_id];
            $block = $this->block($course, $lesson, $quizzes[$lesson->getKey()] ?? null, done: true);
            $doneByDay[$at->toDateString()][] = $block;
            $doneMinutes += $block->minutes;
        }

        // Upcoming: unfinished lessons, course after course.
        $queue = [];
        foreach ($lessonsByCourse as $courseId => $flat) {
            foreach ($flat as $lesson) {
                if (! isset($completedIds[$lesson->getKey()])) {
                    $queue[] = $this->block($courses[$courseId], $lesson, $quizzes[$lesson->getKey()] ?? null, done: false);
                }
            }
        }

        $upcomingByDay = $this->spread($queue, max(0, $goal - $doneMinutes), $weekStart, $today);

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->addDays($i);
            $key = $date->toDateString();
            $days[] = new WeekPlanDay(
                $date,
                $date->equalTo($today),
                [...($doneByDay[$key] ?? []), ...($upcomingByDay[$key] ?? [])],
            );
        }

        return new WeekPlan($days, $doneMinutes, $goal, true);
    }

    /**
     * Fill today and later days with queued blocks until `$remaining` minutes
     * are planned. Each day aims at an even share of what is still unplanned.
     *
     * @param  list<WeekPlanBlock>  $queue
     * @return array<string, list<WeekPlanBlock>>
     */
    private function spread(array $queue, int $remaining, CarbonImmutable $weekStart, CarbonImmutable $today): array
    {
        $byDay = [];
        $planned = 0;
        $next = 0;
        $firstIndex = (int) $weekStart->diffInDays($today, true);

        for ($i = $firstIndex; $i < 7; $i++) {
            $daysLeft = 7 - $i;
            $target = (int) ceil(max(0, $remaining - $planned) / $daysLeft);
            $dayMinutes = 0;
            $key = $weekStart->addDays($i)->toDateString();

            while ($next < count($queue) && $planned < $remaining && $dayMinutes < $target) {
                $block = $queue[$next++];
                $byDay[$key][] = $block;
                $dayMinutes += $block->minutes;
                $planned += $block->minutes;
            }
        }

        return $byDay;
    }

    private function block(Course $course, Lesson $lesson, ?Assessment $quiz, bool $done): WeekPlanBlock
    {
        if ($quiz !== null) {
            return new WeekPlanBlock(
                $course,
                $lesson,
                $quiz->title,
                $quiz->time_limit_minutes ?? self::DEFAULT_QUIZ_MINUTES,
                true,
                $done,
            );
        }

        $minutes = $lesson->duration_seconds !== null
            ? (int) ceil($lesson->duration_seconds / 60)
            : 0;

        return new WeekPlanBlock($course, $lesson, $lesson->title, max(self::MIN_LESSON_MINUTES, $minutes), false, $done);
    }

    /**
     * The published curriculum of several courses, keyed by course id. The same
     * rules as CoursePlayerService::curriculum() (published modules, published
     * lessons, by position), read in one pass.
     *
     * @param  list<int>  $courseIds
     * @return array<int, Collection<int, Module>>
     */
    private function curricula(array $courseIds): array
    {
        return Module::query()
            ->whereIn('course_id', $courseIds)
            ->where('is_published', true)
            ->orderBy('position')
            ->with([
                'lessons' => static fn (Relation $query) => $query
                    ->where('is_published', true)
                    ->orderBy('position'),
            ])
            ->get()
            ->groupBy('course_id')
            ->all();
    }

    /**
     * Published assessments attached directly to these lessons, one per
     * lesson (the lowest id).
     *
     * @param  list<int>  $lessonIds
     * @return array<int, Assessment>
     */
    private function quizzesByLesson(array $lessonIds): array
    {
        if ($lessonIds === []) {
            return [];
        }

        return Assessment::query()
            ->published()
            ->where('assessable_type', (new Lesson)->getMorphClass())
            ->whereIn('assessable_id', $lessonIds)
            ->orderBy('id')
            ->get()
            ->unique('assessable_id')
            ->keyBy('assessable_id')
            ->all();
    }

    /**
     * @return list<WeekPlanDay>
     */
    private function emptyDays(CarbonImmutable $weekStart, CarbonImmutable $today): array
    {
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->addDays($i);
            $days[] = new WeekPlanDay($date, $date->equalTo($today), []);
        }

        return $days;
    }
}
