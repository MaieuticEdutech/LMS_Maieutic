<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\User;
use App\Services\Progress\ProgressCalculator;
use App\Services\Student\StudentDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The student's home (FR-STU-05, FR-STU-07).
 *
 * Read-only, so it holds no state and nothing here is wire:model bound —
 * a full-page Livewire component rather than Blade only because the student
 * shell and My Courses share the same navigation and it keeps them
 * consistent.
 *
 * NOT REACHABLE BY AN INSTRUCTOR OR ADMIN, by route middleware rather than by
 * a check here. Every figure below is scoped to the authenticated user by
 * StudentDashboardService, so even if the route guard were removed this could
 * not show one user another's enrolments.
 */
#[Layout('layouts.student')]
#[Title('Dashboard')]
final class Dashboard extends Component
{
    public function render(StudentDashboardService $dashboard, ProgressCalculator $calculator): View
    {
        /** @var User $student */
        $student = Auth::user();

        return view('livewire.student.dashboard', [
            // The single "pick up where you left off" entry point. Null for a
            // student who has never opened a lesson, which the view treats as
            // its own state rather than as an empty card.
            'continue' => $dashboard->continueLearning($student),
            'enrollments' => $dashboard->activeEnrollments($student),
            'stats' => $dashboard->stats($student),
            /*
             * Overall progress (FR-PROG-07).
             *
             * One aggregate query over the CACHED per-enrollment figures, not
             * a scan of lesson rows. That is what the cache is for: a student
             * with eight courses and one with eight hundred cost the same here
             * (NFR-PERF-04, ADR-008).
             */
            'overall' => $calculator->studentOverall($student),

            /*
             * "Recommended for you" (design_handoff_lms_student_ui §1).
             *
             * There is no recommendation engine, and inventing one would be a
             * feature rather than a design pass. This is the honest reading of
             * the section: published courses the student is not already
             * enrolled in, newest first — genuinely things they could take
             * next, and true in production as well as in review.
             *
             * category and instructors are eager-loaded because
             * preventLazyLoading() is active outside production and the card
             * reads both.
             */
            'recommended' => $this->recommendations($student),
        ]);
    }

    /**
     * @return Collection<int, Course>
     */
    private function recommendations(User $student): Collection
    {
        return Course::query()
            ->where('status', CourseStatus::Published)
            ->whereDoesntHave('enrollments', fn ($q) => $q->where('user_id', $student->getKey()))
            ->with(['category', 'instructors'])
            ->latest('published_at')
            ->limit(3)
            ->get();
    }
}
