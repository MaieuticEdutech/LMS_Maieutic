<?php

declare(strict_types=1);

namespace App\Services\Student;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * What a student sees on their dashboard and My Courses (FR-STU-05, FR-STU-06).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * THE STATUS FILTER HERE MIRRORS EnrollmentAccessService, AND THAT IS A
 * DUPLICATION WORTH UNDERSTANDING RATHER THAN REMOVING.
 *
 * That service answers one question about one course: may this user reach it.
 * It cannot answer "list every course this user may reach" — a per-row call
 * across an unbounded set is the N+1 it was memoised to avoid in the first
 * place.
 *
 * So this builds the same rule as a QUERY: status ∈ {active, completed} and
 * not past its expiry. If that rule ever changes, both must change, and the
 * test suite asserts them against each other so a divergence fails rather
 * than quietly showing a student a course they cannot open.
 *
 * The alternative — listing everything and filtering in PHP through the
 * service — reintroduces the query-per-course this exists to prevent.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class StudentDashboardService
{
    /**
     * Courses this student can actually open, most recently touched first.
     *
     * Ordered by last access rather than enrolment date: someone with eight
     * courses wants the one they were reading yesterday, not the one they
     * bought first.
     *
     * @return Collection<int, Enrollment>
     */
    public function activeEnrollments(User $student): Collection
    {
        /*
         * `instructors` is eager-loaded because the course card names one
         * (design handoff §2 — "instructor 13px"). Without it,
         * preventLazyLoading throws outside production and, in production, a
         * library of twenty courses would fire twenty extra queries to render
         * twenty names.
         */
        return $this->accessibleQuery($student)
            ->with(['course' => static fn (Relation $q) => $q->with(['category', 'instructors'])])
            ->orderByRaw('COALESCE(last_accessed_at, enrolled_at) DESC')
            ->get();
    }

    /**
     * The single "continue learning" entry point (FR-STU-07).
     *
     * The most recently accessed course that is not finished. A completed
     * course is deliberately excluded — offering "continue" on something the
     * student has already finished is noise, and they can still reach it from
     * My Courses.
     */
    public function continueLearning(User $student): ?Enrollment
    {
        return $this->accessibleQuery($student)
            ->where('status', EnrollmentStatus::Active)
            ->whereNotNull('last_accessed_at')
            ->with('course')
            ->orderByDesc('last_accessed_at')
            ->first();
    }

    /**
     * Courses to suggest next (design handoff §1, "Recommended for you").
     *
     * ═════════════════════════════════════════════════════════════════════
     * NOT A RECOMMENDER. NEWEST PUBLISHED COURSES THEY ARE NOT ALREADY IN.
     *
     * There is no signal in this system to recommend from — no ratings, no
     * completion correlations, no topic affinity. Dressing "newest three" up as
     * personalised would be a claim the data cannot support, and the honest
     * version is useful anyway: a student's own courses are already on the
     * screen above, so anything here is new to them.
     *
     * Their own enrolments are excluded with a subquery rather than by loading
     * ids into PHP, so the cost does not grow with the size of their library.
     * ═════════════════════════════════════════════════════════════════════
     *
     * @return Collection<int, Course>
     */
    public function recommended(User $student, int $limit = 3): Collection
    {
        return Course::published()
            ->with(['category', 'instructors'])
            ->whereNotExists(static function (QueryBuilder $query) use ($student): void {
                $query->selectRaw('1')
                    ->from('enrollments')
                    ->whereColumn('enrollments.course_id', 'courses.id')
                    ->where('enrollments.user_id', $student->getKey());
            })
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Headline counts for the dashboard.
     *
     * Three aggregate queries rather than loading every row to count it in
     * PHP — the difference does not matter at eight enrolments and matters a
     * great deal at eight hundred.
     *
     * @return array{enrolled: int, completed: int, in_progress: int}
     */
    public function stats(User $student): array
    {
        $enrolled = $this->accessibleQuery($student)->count();
        $completed = $this->accessibleQuery($student)
            ->where('status', EnrollmentStatus::Completed)
            ->count();

        return [
            'enrolled' => $enrolled,
            'completed' => $completed,
            'in_progress' => $enrolled - $completed,
        ];
    }

    /**
     * The access rule, as a query.
     *
     * Kept private and used by everything above so the three public methods
     * cannot drift apart from each other — only from
     * EnrollmentAccessService, which the tests guard.
     *
     * @return Builder<Enrollment>
     */
    private function accessibleQuery(User $student): Builder
    {
        return Enrollment::query()
            ->where('user_id', $student->getKey())
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->where(static function (Builder $query): void {
                // Expiry is evaluated here rather than trusted from the status
                // column, exactly as EnrollmentAccessService does. A student
                // must not see a course in their list that the player would
                // then refuse to open.
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
