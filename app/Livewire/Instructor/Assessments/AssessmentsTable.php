<?php

declare(strict_types=1);

namespace App\Livewire\Instructor\Assessments;

use App\Models\User;
use App\Services\Instructor\InstructorCourseService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Every assessment across this instructor's assigned courses (FR-ASMT-02,
 * AC-03) — scoped through InstructorCourseService::assessmentsFor(), never
 * Assessment::query() directly.
 */
#[Layout('layouts.instructor', [
    'breadcrumbs' => [
        ['label' => 'Instructor', 'url' => '/instructor'],
        ['label' => 'Assessments', 'url' => null],
    ],
])]
final class AssessmentsTable extends Component
{
    public function render(InstructorCourseService $courses): View
    {
        /** @var User $instructor */
        $instructor = Auth::user();

        return view('livewire.instructor.assessments.table', [
            'assessments' => $courses->assessmentsFor($instructor),
        ]);
    }
}
