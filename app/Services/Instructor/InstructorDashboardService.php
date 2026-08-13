<?php

declare(strict_types=1);

namespace App\Services\Instructor;

use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The instructor dashboard's read model — same shape as
 * App\Services\Admin\DashboardQueryService (single-query counts, eager-load
 * exactly what each panel shows). Every query is scoped through
 * `Course::assignedTo()`/InstructorCourseService, never `Course::query()`
 * (rule S-8-equivalent for this track, architecture.md §8.4).
 *
 * NO FINANCIAL FIGURE ANYWHERE (FR-INS-10) and no progress figure (Phase 9
 * not yet built) — both deliberately absent, not merely unused.
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
        $courseIds = Course::query()->assignedTo($instructor)->pluck('id');

        return Enrollment::query()->whereIn('course_id', $courseIds)->count();
    }

    public function assessmentCount(User $instructor): int
    {
        return $this->courses->assessmentIdsFor($instructor)->count();
    }

    /**
     * Recently submitted/graded attempts across every assigned course's
     * assessments — the closest thing to "recent activity" available
     * without Phase 9's progress engine.
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
