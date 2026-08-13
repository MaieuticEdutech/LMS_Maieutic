<?php

declare(strict_types=1);

namespace App\Livewire\Instructor;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Instructor\InstructorCourseService;
use App\Services\Progress\ProgressCalculator;
use App\Services\Student\CoursePlayerService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * One student's per-module, per-lesson progress within an assigned course
 * (FR-INS-07). Read-only — no completion can be recorded from here, only
 * from the student's own course player (rule S-8-equivalent: this is a
 * report, not another write path into `lesson_progress`).
 *
 * SCOPED TWICE, DELIBERATELY:
 *   1. `$course` is re-resolved through InstructorCourseService, the same
 *      "never trust route model binding" reasoning CourseDetail documents.
 *   2. `$enrollment` is checked against THAT course, not merely policy-
 *      authorised in isolation — a valid enrollment id for a DIFFERENT
 *      course must 404, not silently show the wrong course's curriculum.
 *
 * Reuses `CoursePlayerService` — the same curriculum/progress-map assembly
 * the student's own player uses (bounded queries, NFR-PERF-03) — rather than
 * hand-rolling a second reader that could drift from what a student actually
 * sees.
 *
 * Layout is applied imperatively in render(), not via the static #[Layout]
 * attribute — the breadcrumb trail names the specific course and student,
 * which are only known once mount() has resolved them.
 */
final class StudentProgressDetail extends Component
{
    public Course $course;

    public Enrollment $enrollment;

    public function mount(Course $course, Enrollment $enrollment, InstructorCourseService $courses): void
    {
        /** @var User $instructor */
        $instructor = Auth::user();

        $assigned = $courses->assignedCourse($instructor, $course->slug);

        abort_if($assigned === null, 404);
        abort_if($enrollment->course_id !== $assigned->getKey(), 404);

        $this->authorize('view', $enrollment);

        $this->course = $assigned;
        $this->enrollment = $enrollment->loadMissing('user');
    }

    public function render(CoursePlayerService $player, ProgressCalculator $calculator): View
    {
        $curriculum = $player->curriculum($this->course);
        $progress = $player->progressMap($this->enrollment);

        $student = $this->enrollment->user;

        if ($student === null) {
            abort(404);
        }

        return view('livewire.instructor.student-progress-detail', [
            'curriculum' => $curriculum,
            'progress' => $progress,
            'moduleProgress' => $player->moduleProgressMap($curriculum, $progress, $calculator),
        ])->layout('layouts.instructor', [
            'breadcrumbs' => [
                ['label' => 'Instructor', 'url' => '/instructor'],
                ['label' => 'My courses', 'url' => '/instructor/courses'],
                ['label' => $this->course->title, 'url' => route('instructor.courses.show', $this->course)],
                ['label' => $student->name, 'url' => null],
            ],
        ]);
    }
}
