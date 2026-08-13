<?php

declare(strict_types=1);

namespace App\Livewire\Instructor;

use App\Models\Course;
use App\Models\User;
use App\Services\Instructor\InstructorCourseService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * One assigned course's detail: enrolled students and its assessments.
 *
 * SCOPED BY RE-RESOLVING THROUGH InstructorCourseService, NOT BY TRUSTING
 * ROUTE MODEL BINDING (architecture.md §8.4). Laravel would happily bind
 * ANY course slug in the URL; re-fetching through
 * `assignedCourse($instructor, $slug)` is what turns "an instructor typed
 * another instructor's course slug" into a 404 instead of a data leak —
 * the same reasoning `Course::assignedTo()` exists for at all (AC-03).
 *
 * Per-student and course-level progress (FR-INS-02, FR-INS-03) read the
 * cached `enrollments.progress_percentage` column — the same figure the
 * student's own dashboard shows them, never recounted from `lesson_progress`
 * here.
 */
#[Layout('layouts.instructor', [
    'breadcrumbs' => [
        ['label' => 'Instructor', 'url' => '/instructor'],
        ['label' => 'My courses', 'url' => '/instructor/courses'],
        ['label' => 'Detail', 'url' => null],
    ],
])]
final class CourseDetail extends Component
{
    public Course $course;

    public function mount(Course $course, InstructorCourseService $courses): void
    {
        /** @var User $instructor */
        $instructor = Auth::user();

        $assigned = $courses->assignedCourse($instructor, $course->slug);

        abort_if($assigned === null, 404);

        $this->course = $assigned;
    }

    public function render(InstructorCourseService $courses): View
    {
        return view('livewire.instructor.course-detail', [
            'students' => $courses->enrolledStudents($this->course),
            'assessments' => $courses->assessmentsForCourse($this->course),
            'averageProgress' => $courses->averageProgressForCourse($this->course),
        ]);
    }
}
