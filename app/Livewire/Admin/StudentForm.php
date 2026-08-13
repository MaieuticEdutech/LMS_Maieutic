<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\CreateStudent;
use App\Actions\Admin\UpdateStudent;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', [
    'breadcrumbs' => [
        ['label' => 'Administration', 'url' => '/admin'],
        ['label' => 'Students', 'url' => '/admin/students'],
        ['label' => 'Form', 'url' => null],
    ],
])]
final class StudentForm extends Component
{
    public ?User $student = null;

    public string $name = '';

    public string $email = '';

    public ?string $phone = null;

    public function mount(?User $student = null): void
    {
        if ($student !== null) {
            abort_unless($student->hasRole(UserRole::Student), 404);
            $this->authorize('update', $student);

            $this->student = $student;
            $this->name = $student->name;
            $this->email = $student->email;
            $this->phone = $student->phone;

            return;
        }

        $this->authorize('create', User::class);
    }

    public function save(CreateStudent $createStudent, UpdateStudent $updateStudent): mixed
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->student?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        if ($this->student !== null) {
            $updateStudent->handle($this->student, $validated, $actor);
            session()->flash('status', "Student {$this->student->email} updated.");

            return redirect()->route('admin.students.show', $this->student);
        }

        $student = $createStudent->handle($validated, $actor);
        session()->flash('status', "Student {$student->email} created — an activation link has been sent.");

        return redirect()->route('admin.students.show', $student);
    }

    public function render(): View
    {
        return view('livewire.admin.student-form');
    }
}
