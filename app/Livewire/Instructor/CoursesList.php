<?php

declare(strict_types=1);

namespace App\Livewire\Instructor;

use App\Models\User;
use App\Services\Instructor\InstructorCourseService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Courses this instructor is assigned to (FR-RBAC-04, AC-03) — read-only.
 * FR-INS-08: instructors do not create, edit or delete courses here, only
 * view them and reach their assessments/students.
 */
#[Layout('layouts.instructor', [
    'breadcrumbs' => [
        ['label' => 'Instructor', 'url' => '/instructor'],
        ['label' => 'My courses', 'url' => null],
    ],
])]
final class CoursesList extends Component
{
    public function render(InstructorCourseService $courses): View
    {
        /** @var User $instructor */
        $instructor = Auth::user();

        return view('livewire.instructor.courses-list', [
            'courses' => $courses->assignedCourses($instructor),
        ]);
    }
}
