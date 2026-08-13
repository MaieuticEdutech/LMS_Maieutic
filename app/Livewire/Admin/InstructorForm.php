<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\CreateInstructor;
use App\Actions\Admin\UpdateInstructor;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', [
    'breadcrumbs' => [
        ['label' => 'Administration', 'url' => '/admin'],
        ['label' => 'Instructors', 'url' => '/admin/instructors'],
        ['label' => 'Form', 'url' => null],
    ],
])]
final class InstructorForm extends Component
{
    public ?User $instructor = null;

    public string $name = '';

    public string $email = '';

    public ?string $phone = null;

    public ?string $headline = null;

    public ?string $bio = null;

    public function mount(?User $instructor = null): void
    {
        if ($instructor !== null) {
            abort_unless($instructor->hasRole(UserRole::Instructor), 404);
            $this->authorize('update', $instructor);

            // Explicit eager load — `preventLazyLoading` (AppServiceProvider)
            // throws on a lazy `$instructor->instructorProfile` access outside
            // production; `loadMissing` is the deliberate load that satisfies it.
            $instructor->loadMissing('instructorProfile');

            $this->instructor = $instructor;
            $this->name = $instructor->name;
            $this->email = $instructor->email;
            $this->phone = $instructor->phone;
            $this->headline = $instructor->instructorProfile?->headline;
            $this->bio = $instructor->instructorProfile?->bio;

            return;
        }

        $this->authorize('create', User::class);
    }

    public function save(CreateInstructor $createInstructor, UpdateInstructor $updateInstructor): mixed
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->instructor?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'headline' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
        ]);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        if ($this->instructor !== null) {
            $updateInstructor->handle($this->instructor, $validated, $actor);
            session()->flash('status', "Instructor {$this->instructor->email} updated.");

            return redirect()->route('admin.instructors.show', $this->instructor);
        }

        $instructor = $createInstructor->handle($validated, $actor);
        session()->flash('status', "Instructor {$instructor->email} created — an activation link has been sent.");

        return redirect()->route('admin.instructors.show', $instructor);
    }

    public function render(): View
    {
        return view('livewire.admin.instructor-form');
    }
}
