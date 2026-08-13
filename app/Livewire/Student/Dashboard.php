<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Models\User;
use App\Services\Student\StudentDashboardService;
use Illuminate\Contracts\View\View;
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
#[Layout('layouts.app')]
#[Title('Dashboard')]
final class Dashboard extends Component
{
    public function render(StudentDashboardService $dashboard): View
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
        ]);
    }
}
