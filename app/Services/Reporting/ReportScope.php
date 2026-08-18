<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The single definition of "which courses may this actor report on"
 * (FR-RPT-07, architecture.md §19).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * SCOPE IS APPLIED INSIDE THE QUERY SERVICE, NEVER AT THE CALL SITE.
 *
 * Every report method takes the acting user and routes its base query through
 * here. That is the same reasoning as EnrollmentAccessService: with one
 * definition, leaking another instructor's data requires actively bypassing
 * the standard entry point rather than merely forgetting a WHERE clause in
 * one of five report services.
 *
 * It matters more here than almost anywhere else in the system. A report is
 * an aggregate — a single missing scope does not leak one row, it leaks the
 * whole table, and it does so in a CSV somebody then emails onward.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * Financial scope is NOT expressed here. Instructors see no revenue data at
 * all (FR-RPT-07, FR-INS-10), and that is enforced by there being no
 * instructor route to the revenue report — an absent screen cannot leak, and
 * a boolean flag on a shared one eventually will.
 */
final class ReportScope
{
    /**
     * Course ids the actor may see, or null for "no restriction".
     *
     * Null rather than a list of every id: a super admin reporting across a
     * thousand courses should not have those ids marshalled into PHP and sent
     * back as a WHERE IN.
     *
     * @return list<int>|null
     */
    public function courseIds(User $actor): ?array
    {
        if ($actor->isSuperAdmin()) {
            return null;
        }

        if (! $actor->isInstructor()) {
            // Deny-safe: any other role reports on nothing. A student reaching
            // a report service at all is a routing mistake, and an empty scope
            // fails closed while it is found.
            return [];
        }

        return array_values(
            Course::query()
                ->assignedTo($actor)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all(),
        );
    }

    /**
     * Constrain a query to the actor's courses.
     *
     * Accepts either builder: reports aggregate through the query builder
     * (COUNT ... FILTER, AVG) where an Eloquent model buys nothing and only
     * makes the aggregate columns look like undeclared model properties.
     *
     * @template TQuery of Builder<*>|QueryBuilder
     *
     * @param  TQuery  $query
     * @param  string  $column  the course-id column on the query's table
     * @return TQuery
     */
    public function apply(Builder|QueryBuilder $query, User $actor, string $column = 'course_id'): Builder|QueryBuilder
    {
        $ids = $this->courseIds($actor);

        if ($ids === null) {
            return $query;
        }

        $query->whereIn($column, $ids);

        return $query;
    }

    /**
     * Whether the actor may see money at all (FR-RPT-07).
     *
     * Consulted by the reports that carry financial columns, so the rule is
     * stated once rather than re-derived from the role in each of them.
     */
    public function maySeeFinancials(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }
}
