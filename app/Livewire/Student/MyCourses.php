<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Models\Enrollment;
use App\Models\User;
use App\Services\Student\StudentDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Every course this student can open (FR-STU-06).
 *
 * The dashboard shows a summary and one "continue" entry point; this is the
 * full list, ordered by what they touched most recently.
 *
 * COURSES THEY CAN NO LONGER OPEN ARE ABSENT, not greyed out. A revoked or
 * expired enrollment simply does not appear, because the alternative — a card
 * that looks like a course but 403s when clicked — teaches students the
 * product is broken rather than that their access ended. Phase 12 adds a
 * purchase history, which is the right place for "you used to have this".
 */
#[Layout('layouts.student')]
#[Title('My Learning')]
final class MyCourses extends Component
{
    /**
     * Which tab is showing: 'all', 'in-progress' or 'completed'.
     *
     * ═════════════════════════════════════════════════════════════════════
     * FILTERED IN PHP, NOT IN A SECOND QUERY.
     *
     * The three tabs are subsets of ONE list the student can open, and that
     * list is already loaded — re-querying per tab would mean a second place
     * where the access rule is written, which is exactly the divergence
     * StudentDashboardService exists to prevent. A student's enrolment count
     * is small and bounded by what a person can buy; this is not the place
     * that needs a query optimiser.
     * ═════════════════════════════════════════════════════════════════════
     *
     * In the URL so a tab can be linked and survives a refresh.
     */
    #[Url(as: 'show')]
    public string $filter = 'all';

    /**
     * The tabs, in the mockup's order.
     *
     * @return array<string, string>
     */
    public function tabs(): array
    {
        return [
            'all' => 'All',
            'in-progress' => 'In progress',
            'completed' => 'Completed',
        ];
    }

    public function render(StudentDashboardService $dashboard): View
    {
        /** @var User $student */
        $student = Auth::user();

        $enrollments = $dashboard->activeEnrollments($student);

        return view('livewire.student.my-courses', [
            // The unfiltered list drives the empty state and the tab counts, so
            // "you have nothing at all" stays distinguishable from "nothing
            // matches this tab" — two different messages for two different
            // situations.
            'enrollments' => $enrollments,
            'visible' => $this->applyFilter($enrollments),
        ]);
    }

    /**
     * @param  Collection<int, Enrollment>  $enrollments
     * @return Collection<int, Enrollment>
     */
    private function applyFilter(Collection $enrollments): Collection
    {
        /*
         * "Completed" is keyed on completed_at rather than on the percentage.
         * A course can read 100% while its final test is still outstanding, and
         * calling that finished would tell a student they had earned something
         * they had not (ADR-008 — the enrollment row is the fact).
         *
         * An unrecognised value from the URL falls through to everything,
         * rather than showing an empty screen for a typo.
         */
        return match ($this->filter) {
            'in-progress' => $enrollments->filter(static fn (Enrollment $e): bool => $e->completed_at === null),
            'completed' => $enrollments->filter(static fn (Enrollment $e): bool => $e->completed_at !== null),
            default => $enrollments,
        };
    }
}
