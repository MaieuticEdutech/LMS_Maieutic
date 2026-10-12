<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\CreateInstructor;
use App\Actions\Admin\SetInstructorPhoto;
use App\Actions\Admin\UpdateInstructor;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.admin', [
    'breadcrumbs' => [
        ['label' => 'Administration', 'url' => '/admin'],
        ['label' => 'Instructors', 'url' => '/admin/instructors'],
        ['label' => 'Form', 'url' => null],
    ],
])]
final class InstructorForm extends Component
{
    use WithFileUploads;

    public ?User $instructor = null;

    /** A newly chosen public profile photo, saved with the rest of the form. */
    public ?TemporaryUploadedFile $photo = null;

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
            $instructor->loadMissing('instructorProfile.photo');

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

    public function save(CreateInstructor $createInstructor, UpdateInstructor $updateInstructor, SetInstructorPhoto $setPhoto): mixed
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->instructor?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'headline' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            // A first, friendly check. MediaStorageService enforces the real
            // limits (size ceiling, extension list, sniffed mime type).
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        unset($validated['photo']);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $existing = $this->instructor;

        if ($existing !== null) {
            $updateInstructor->handle($existing, $validated, $actor);
            $this->storePhoto($setPhoto, $existing, $actor);
            session()->flash('status', "Instructor {$existing->email} updated.");

            return redirect()->route('admin.instructors.show', $existing);
        }

        $instructor = $createInstructor->handle($validated, $actor);
        $this->storePhoto($setPhoto, $instructor, $actor);
        session()->flash('status', "Instructor {$instructor->email} created — an activation link has been sent.");

        return redirect()->route('admin.instructors.show', $instructor);
    }

    public function removePhoto(SetInstructorPhoto $setPhoto): void
    {
        abort_unless($this->instructor !== null, 404);
        $this->authorize('update', $this->instructor);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $setPhoto->remove($this->instructor, $actor);
        $this->instructor->load('instructorProfile.photo');
    }

    private function storePhoto(SetInstructorPhoto $setPhoto, User $instructor, User $actor): void
    {
        if ($this->photo !== null) {
            $setPhoto->handle($instructor, $this->photo, $actor);
        }
    }

    public function render(): View
    {
        return view('livewire.admin.instructor-form');
    }
}
