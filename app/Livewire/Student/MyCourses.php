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
#[Layout('layouts.app')]
#[Title('My courses')]
final class MyCourses extends Component
{
    public function render(StudentDashboardService $dashboard): View
    {
        /** @var User $student */
        $student = Auth::user();

        return view('livewire.student.my-courses', [
            'enrollments' => $dashboard->activeEnrollments($student),
        ]);
    }
}
