<?php

declare(strict_types=1);

namespace App\Services\Instructor;

use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

/**
 * The instructor dashboard's read model — same shape as
 * App\Services\Admin\DashboardQueryService (single-query counts, eager-load
 * exactly what each panel shows). Every query is scoped through
 * `Course::assignedTo()`/InstructorCourseService, never `Course::query()`
 * (rule S-8-equivalent for this track, architecture.md §8.4).
 *
 * NO FINANCIAL FIGURE ANYWHERE, EVER (FR-INS-10).
 */
final class InstructorDashboardService
{
    public function __construct(private readonly InstructorCourseService $courses) {}

    public function assignedCourseCount(User $instructor): int
    {
        return Course::query()->assignedTo($instructor)->count();
    }

    public function studentCount(User $instructor): int
    {
        return Enrollment::query()->whereIn('course_id', $this->assignedCourseIds($instructor))->count();
    }

    public function assessmentCount(User $instructor): int
    {
        return $this->courses->assessmentIdsFor($instructor)->count();
    }

    /**
     * FR-INS-02's "average progress" figure — a mean of the cached
     * `enrollments.progress_percentage` column across every enrollment in
     * every assigned course, the same reasoning
     * `ProgressCalculator::studentOverall()` applies to one student's
     * courses (a mean of course percentages, read from the cache rather than
     * recounted from `lesson_progress`).
     */
    public function averageProgress(User $instructor): int
    {
        $average = Enrollment::query()
            ->whereIn('course_id', $this->assignedCourseIds($instructor))
            ->avg('progress_percentage');

        return (int) round((float) ($average ?? 0));
    }

    /**
     * @return BaseCollection<int, int>
     */
    private function assignedCourseIds(User $instructor): BaseCollection
    {
        return Course::query()->assignedTo($instructor)->pluck('id');
    }

    /**
     * Recently submitted/graded attempts across every assigned course's
     * assessments — FR-INS-02's "recent assessment activity".
     *
     * @return Collection<int, AssessmentAttempt>
     */
    public function recentAttempts(User $instructor, int $limit = 5): Collection
    {
        return AssessmentAttempt::query()
            ->whereIn('assessment_id', $this->courses->assessmentIdsFor($instructor))
            ->whereNotNull('submitted_at')
            ->with(['user:id,name', 'assessment:id,title'])
            ->latest('submitted_at')
            ->limit($limit)
            ->get();
    }
}
