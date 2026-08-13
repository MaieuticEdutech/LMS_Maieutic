<?php

declare(strict_types=1);

namespace App\Livewire\Instructor;

use App\Models\User;
use App\Services\Instructor\InstructorDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The instructor's operational home (phases.md Phase 10). No breadcrumb —
 * landing page, not a sub-page. Every figure is scoped to assigned courses
 * via InstructorDashboardService; no financial figure appears anywhere
 * (FR-INS-10), and no progress figure yet (Phase 9 not built).
 */
#[Layout('layouts.instructor')]
final class Dashboard extends Component
{
    public function render(InstructorDashboardService $dashboard): View
    {
        /** @var User $instructor */
        $instructor = Auth::user();

        return view('livewire.instructor.dashboard', [
            'assignedCourseCount' => $dashboard->assignedCourseCount($instructor),
            'studentCount' => $dashboard->studentCount($instructor),
            'assessmentCount' => $dashboard->assessmentCount($instructor),
            'recentAttempts' => $dashboard->recentAttempts($instructor),
        ]);
    }
}
