<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Enrollments per course, per period, by source (FR-RPT-01).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * THE SOURCE SPLIT IS THE REPORT. A TOTAL WOULD NOT BE WORTH RUNNING.
 *
 * `EnrollmentSource` is purchase | admin_grant | import, and those are three
 * different facts about the business. Forty enrollments is a good month if
 * they were bought and a cost centre if they were granted. A single count
 * cannot tell those apart, so every row here carries the breakdown.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * Aggregated in SQL against the indexed course_id / source columns, never by
 * loading enrollments and counting them in PHP (architecture.md §19) — a
 * course with fifty thousand students must cost the same as one with five.
 *
 * Through the QUERY builder, not Eloquent: nothing here needs a model, and a
 * hydrated Enrollment carrying columns called `purchase` and `total` is a
 * model pretending to be a row it is not.
 */
final class EnrollmentReport
{
    public function __construct(private readonly ReportScope $scope) {}

    /**
     * One row per course, with the source split and a total.
     *
     * @return Collection<int, array{course: string, purchase: int, admin_grant: int, import: int, total: int}>
     */
    public function perCourse(User $actor, DateRange $range): Collection
    {
        $query = DB::table('enrollments')
            ->join('courses', 'courses.id', '=', 'enrollments.course_id')
            ->select('courses.title as course')
            // COUNT ... FILTER rather than three round trips: one pass over
            // the index, and the row's parts cannot disagree with its total.
            ->selectRaw("COUNT(*) FILTER (WHERE enrollments.source = 'purchase') AS purchase")
            ->selectRaw("COUNT(*) FILTER (WHERE enrollments.source = 'admin_grant') AS admin_grant")
            ->selectRaw("COUNT(*) FILTER (WHERE enrollments.source = 'import') AS import")
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('courses.title')
            ->orderByDesc('total');

        $this->scope->apply($query, $actor, 'enrollments.course_id');
        $this->applyRange($query, $range);

        return $query->get()->map(static fn (object $row): array => [
            'course' => (string) $row->course,
            'purchase' => (int) $row->purchase,
            'admin_grant' => (int) $row->admin_grant,
            'import' => (int) $row->import,
            'total' => (int) $row->total,
        ]);
    }

    /**
     * The same figures collapsed to one row, for the header strip.
     *
     * @return array{purchase: int, admin_grant: int, import: int, total: int}
     */
    public function totals(User $actor, DateRange $range): array
    {
        $rows = $this->perCourse($actor, $range);

        return [
            'purchase' => (int) $rows->sum('purchase'),
            'admin_grant' => (int) $rows->sum('admin_grant'),
            'import' => (int) $rows->sum('import'),
            'total' => (int) $rows->sum('total'),
        ];
    }

    /**
     * Enrollments per month, so the table has a trend behind it.
     *
     * @return Collection<int, array{period: string, total: int}>
     */
    public function perPeriod(User $actor, DateRange $range): Collection
    {
        $query = DB::table('enrollments')
            ->selectRaw("TO_CHAR(DATE_TRUNC('month', enrolled_at), 'YYYY-MM') AS period")
            ->selectRaw('COUNT(*) AS total')
            ->whereNotNull('enrolled_at')
            ->groupBy('period')
            ->orderBy('period');

        $this->scope->apply($query, $actor, 'course_id');
        $this->applyRange($query, $range);

        return $query->get()->map(static fn (object $row): array => [
            'period' => (string) $row->period,
            'total' => (int) $row->total,
        ]);
    }

    private function applyRange(Builder $query, DateRange $range): void
    {
        // On enrolled_at, not created_at: the report is about when access
        // began, which is the fact the business acted on.
        if ($range->from !== null) {
            $query->where('enrollments.enrolled_at', '>=', $range->from);
        }

        if ($range->to !== null) {
            $query->where('enrollments.enrolled_at', '<=', $range->to);
        }
    }
}
