<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Per student: enrollments, progress, attempts, scores, last activity
 * (FR-RPT-05).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * LAST ACTIVITY IS THE ACTIONABLE FIELD. THE REST IS CONTEXT FOR IT.
 *
 * A student who paid three weeks ago and has never opened a lesson is a
 * refund risk and a support conversation — and nothing else in the system
 * surfaces them. Everything else on the row is what you want in front of you
 * when you have that conversation, or when they write in asking "where am I
 * up to?".
 * ═════════════════════════════════════════════════════════════════════════
 *
 * BUILT FROM ENROLLMENTS OUTWARD, NOT FROM USERS INWARD.
 *
 * An instructor may see a student because that student is enrolled in a
 * course they teach — not because the student exists. Starting at the users
 * table and filtering afterwards would list every student on the platform to
 * anyone who reached this method, which is exactly the failure FR-RPT-07
 * describes. Grouping the enrollment rows the scope already permits makes the
 * unscoped version impossible to write by accident.
 */
final class StudentReport
{
    public function __construct(private readonly ReportScope $scope) {}

    /**
     * @return Collection<int, array{student: string, email: string, enrollments: int, average_progress: int, completed: int, attempts: int, average_score: float|null, last_activity: string}>
     */
    public function rows(User $actor, DateRange $range, string $search = ''): Collection
    {
        $query = DB::table('enrollments')
            ->join('users', 'users.id', '=', 'enrollments.user_id')
            ->select('users.id', 'users.name', 'users.email')
            ->selectRaw('COUNT(*) AS enrollments')
            ->selectRaw('COALESCE(ROUND(AVG(enrollments.progress_percentage)), 0) AS average_progress')
            ->selectRaw('COUNT(*) FILTER (WHERE enrollments.completed_at IS NOT NULL) AS completed')
            ->selectRaw('MAX(enrollments.last_accessed_at) AS last_activity')
            /*
             * Attempt figures joined as a pre-aggregated subquery rather than
             * a second join on attempts: joining both would multiply the
             * enrollment rows by the attempt rows and quietly inflate every
             * count on this report — the classic fan-out.
             */
            ->leftJoinSub(
                DB::table('assessment_attempts')
                    ->select('enrollment_id')
                    ->selectRaw('COUNT(*) AS attempt_count')
                    ->selectRaw('AVG(score_percentage) AS average_score')
                    ->where('status', 'graded')
                    ->groupBy('enrollment_id'),
                'graded',
                'graded.enrollment_id',
                '=',
                'enrollments.id',
            )
            ->selectRaw('COALESCE(SUM(graded.attempt_count), 0) AS attempts')
            ->selectRaw('AVG(graded.average_score) AS average_score')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderBy('users.name');

        $this->scope->apply($query, $actor, 'enrollments.course_id');

        if ($range->from !== null) {
            $query->where('enrollments.enrolled_at', '>=', $range->from);
        }

        if ($range->to !== null) {
            $query->where('enrollments.enrolled_at', '<=', $range->to);
        }

        if ($search !== '') {
            $query->where(function (\Illuminate\Database\Query\Builder $q) use ($search): void {
                $q->where('users.name', 'like', '%'.$search.'%')
                    ->orWhere('users.email', 'like', '%'.$search.'%');
            });
        }

        return $query->get()->map(static function (object $row): array {
            $attempts = (int) $row->attempts;

            return [
                'student' => (string) $row->name,
                'email' => (string) $row->email,
                'enrollments' => (int) $row->enrollments,
                'average_progress' => (int) $row->average_progress,
                'completed' => (int) $row->completed,
                'attempts' => $attempts,
                // Null, not zero, when nothing has been graded: a student who
                // has sat no assessment has no average, and printing 0% reads
                // as having failed everything.
                'average_score' => $attempts > 0 && $row->average_score !== null
                    ? round((float) $row->average_score, 1)
                    : null,
                'last_activity' => $row->last_activity !== null
                    ? Carbon::parse((string) $row->last_activity)->diffForHumans()
                    : 'Never opened a lesson',
            ];
        });
    }
}
