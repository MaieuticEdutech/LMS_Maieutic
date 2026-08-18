<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Per course: enrolled, started, in progress, completed, average % complete
 * (FR-RPT-03).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * READ THIS AS A FUNNEL, NOT FIVE NUMBERS. THAT IS WHERE ITS VALUE IS.
 *
 *   many enrolled, few STARTED     → the first lesson is not landing, or the
 *                                    course looks harder than it is
 *   many started, few COMPLETED    → find where they stall; that module is
 *                                    the one to rewrite
 *   high average %, low completion → they finish the content and never trip
 *                                    the completion rule — usually a defect,
 *                                    not a behaviour
 *
 * It tells an instructor which course to fix AND roughly where, which no
 * single completion percentage does.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * Every figure comes from the CACHED progress columns on `enrollments`
 * (progress_percentage, completed_lessons_count), which Phase 9's
 * ProgressCalculator maintains. Recomputing them here by scanning
 * lesson_progress would make the report cost grow with total lessons watched
 * across the platform — the exact thing the cache exists to prevent
 * (ADR-008, NFR-PERF-04).
 */
final class CourseProgressReport
{
    public function __construct(private readonly ReportScope $scope) {}

    /**
     * @return Collection<int, array{course: string, enrolled: int, started: int, in_progress: int, completed: int, average: int}>
     */
    public function perCourse(User $actor, DateRange $range): Collection
    {
        $query = DB::table('enrollments')
            ->join('courses', 'courses.id', '=', 'enrollments.course_id')
            ->select('courses.title as course')
            ->selectRaw('COUNT(*) AS enrolled')
            // "Started" is any progress at all, so a student who opened one
            // lesson counts — the distinction the funnel turns on is between
            // never opening the course and opening it.
            ->selectRaw('COUNT(*) FILTER (WHERE enrollments.progress_percentage > 0) AS started')
            ->selectRaw('COUNT(*) FILTER (WHERE enrollments.progress_percentage > 0 AND enrollments.completed_at IS NULL) AS in_progress')
            ->selectRaw('COUNT(*) FILTER (WHERE enrollments.completed_at IS NOT NULL) AS completed')
            ->selectRaw('COALESCE(ROUND(AVG(enrollments.progress_percentage)), 0) AS average')
            ->groupBy('courses.title')
            ->orderByDesc('enrolled');

        $this->scope->apply($query, $actor, 'enrollments.course_id');

        if ($range->from !== null) {
            $query->where('enrollments.enrolled_at', '>=', $range->from);
        }

        if ($range->to !== null) {
            $query->where('enrollments.enrolled_at', '<=', $range->to);
        }

        return $query->get()->map(static fn (object $row): array => [
            'course' => (string) $row->course,
            'enrolled' => (int) $row->enrolled,
            'started' => (int) $row->started,
            'in_progress' => (int) $row->in_progress,
            'completed' => (int) $row->completed,
            'average' => (int) $row->average,
        ]);
    }
}
