<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\AssignInstructorToCourse;
use App\Actions\Admin\UnassignInstructorFromCourse;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

/**
 * Embedded within {@see InstructorDetail} (never routed to directly) — the
 * course-assignment section of an instructor's detail page.
 *
 * Authorisation is per-course, not per-instructor: each mutating method
 * resolves the target `Course` first and checks `CoursePolicy::manageInstructors`
 * against IT, matching how `InstructorForm` briefs describe the gate (pass the
 * Course instance, not the User).
 */
final class CourseInstructorAssignment extends Component
{
    public User $instructor;

    /**
     * Bound to the assign `<select>`. String, not int — Livewire binds
     * `<select>` values as strings, and casting only where it is consumed
     * (`assign()`) keeps the property itself a faithful mirror of the form
     * field.
     */
    public string $courseToAssign = '';

    public function mount(User $instructor): void
    {
        abort_unless($instructor->hasRole(UserRole::Instructor), 404);

        $this->instructor = $instructor;
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    /**
     * Courses this instructor is currently assigned to.
     *
     * Reads through `Course::assignedTo()` — the single entry point every
     * instructor-scoped query in the system begins from (architecture.md
     * §8.4) — rather than querying the pivot table directly.
     *
     * @return Collection<int, Course>
     */
    public function assignedCourses(): Collection
    {
        return Course::query()->assignedTo($this->instructor)->orderBy('title')->get();
    }

    /**
     * Courses NOT yet assigned to this instructor — the candidate list for
     * the assign control.
     *
     * @return Collection<int, Course>
     */
    public function availableCourses(): Collection
    {
        return Course::query()
            ->whereDoesntHave(
                'instructors',
                fn (Builder $query) => $query->whereKey($this->instructor->getKey()),
            )
            ->orderBy('title')
            ->get();
    }

    public function assign(AssignInstructorToCourse $assignInstructorToCourse): void
    {
        if ($this->courseToAssign === '') {
            return;
        }

        $course = Course::query()->findOrFail((int) $this->courseToAssign);
        $this->authorize('manageInstructors', $course);

        $assignInstructorToCourse->handle($course, $this->instructor, $this->actor());

        $this->courseToAssign = '';
        session()->flash('status', "Assigned {$this->instructor->name} to \"{$course->title}\".");
    }

    public function unassign(int $courseId, UnassignInstructorFromCourse $unassignInstructorFromCourse): void
    {
        $course = Course::query()->findOrFail($courseId);
        $this->authorize('manageInstructors', $course);

        $unassignInstructorFromCourse->handle($course, $this->instructor, $this->actor());

        session()->flash('status', "Unassigned {$this->instructor->name} from \"{$course->title}\".");
    }

    public function render(): View
    {
        return view('livewire.admin.course-instructor-assignment', [
            'assignedCourses' => $this->assignedCourses(),
            'availableCourses' => $this->availableCourses(),
        ]);
    }
}
