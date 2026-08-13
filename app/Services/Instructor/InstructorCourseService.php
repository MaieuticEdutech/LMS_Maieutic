<?php

declare(strict_types=1);

namespace App\Services\Instructor;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

/**
 * THE ENTRY POINT FOR EVERY INSTRUCTOR-SCOPED COURSE AND ASSESSMENT QUERY
 * (architecture.md §8.4, FR-RBAC-04, AC-03). Every method here begins from
 * `Course::assignedTo($user)`, never `Course::query()` — that is what makes
 * scope leakage require actively bypassing this service rather than merely
 * forgetting a WHERE clause, the same discipline `EnrollmentAccessService`
 * enforces for content access.
 *
 * NO PROGRESS FIGURES HERE. "Average progress" per course/student is Phase
 * 9 territory (`ProgressCalculator`) — deliberately not built ahead of it.
 * Course detail and the students list below show enrollment and assessment
 * facts only.
 */
final class InstructorCourseService
{
    /**
     * @return Collection<int, Course>
     */
    public function assignedCourses(User $instructor): Collection
    {
        return Course::query()
            ->assignedTo($instructor)
            ->withCount(['modules', 'lessons'])
            ->orderBy('title')
            ->get();
    }

    /**
     * Deny-safe: null means "not assigned", and every caller must treat
     * that as 404/403, never as "show it anyway".
     */
    public function assignedCourse(User $instructor, string $slug): ?Course
    {
        return Course::query()
            ->assignedTo($instructor)
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Students enrolled in an assigned course — FR-INS-17 read access, not a
     * write path. Every status is shown (not just active) because a
     * suspended or expired enrollment is still a fact about who took this
     * course, same reasoning DeleteCourse's enrollment guard applies.
     *
     * @return Collection<int, Enrollment>
     */
    public function enrolledStudents(Course $course): Collection
    {
        return Enrollment::query()
            ->where('course_id', $course->getKey())
            ->with('user')
            ->orderByDesc('enrolled_at')
            ->get();
    }

    public function studentCount(Course $course): int
    {
        return Enrollment::query()->where('course_id', $course->getKey())->count();
    }

    /**
     * Every assessment attached anywhere inside this instructor's assigned
     * courses — a Course itself (final test), a Module of one, or a Lesson
     * within one. Three lookups rather than a single join: `assessable_type`
     * differs per row, so there is no shared foreign key to join on.
     *
     * @return Collection<int, Assessment>
     */
    public function assessmentsFor(User $instructor): Collection
    {
        return Assessment::query()
            ->whereIn('id', $this->assessmentIdsFor($instructor))
            ->with('assessable')
            ->get();
    }

    /**
     * Every assessment attached anywhere inside ONE course — its own final
     * test, a module quiz, or a lesson quiz. Same three-way lookup as
     * assessmentsFor(), scoped to a single already-verified-assigned course
     * rather than every course this instructor teaches.
     *
     * @return Collection<int, Assessment>
     */
    public function assessmentsForCourse(Course $course): Collection
    {
        $moduleIds = Module::query()->where('course_id', $course->getKey())->pluck('id');
        $lessonIds = Lesson::query()->whereIn('module_id', $moduleIds)->pluck('id');

        return Assessment::query()
            ->where(function ($query) use ($course, $moduleIds, $lessonIds): void {
                $query->where(fn ($q) => $q->where('assessable_type', Course::class)->where('assessable_id', $course->getKey()))
                    ->orWhere(fn ($q) => $q->where('assessable_type', Module::class)->whereIn('assessable_id', $moduleIds))
                    ->orWhere(fn ($q) => $q->where('assessable_type', Lesson::class)->whereIn('assessable_id', $lessonIds));
            })
            ->get();
    }

    /**
     * @return BaseCollection<int, int>
     */
    public function assessmentIdsFor(User $instructor): BaseCollection
    {
        $courseIds = Course::query()->assignedTo($instructor)->pluck('id');
        $moduleIds = Module::query()->whereIn('course_id', $courseIds)->pluck('id');
        $lessonIds = Lesson::query()->whereIn('module_id', $moduleIds)->pluck('id');

        return Assessment::query()
            ->where(function ($query) use ($courseIds, $moduleIds, $lessonIds): void {
                $query->where(fn ($q) => $q->where('assessable_type', Course::class)->whereIn('assessable_id', $courseIds))
                    ->orWhere(fn ($q) => $q->where('assessable_type', Module::class)->whereIn('assessable_id', $moduleIds))
                    ->orWhere(fn ($q) => $q->where('assessable_type', Lesson::class)->whereIn('assessable_id', $lessonIds));
            })
            ->pluck('id');
    }
}
