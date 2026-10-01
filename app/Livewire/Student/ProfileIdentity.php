<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The read-only identity card at the top of the profile page — photo (or
 * initials), name, email, join date, role and status.
 *
 * A LIVEWIRE COMPONENT FOR WHAT IS OTHERWISE STATIC CONTENT, AND THAT NEEDS
 * EXPLAINING. profile/show.blade.php is a plain Blade view; it reads
 * auth()->user() once, when the full page loads, and never again. Uploading
 * a photo happens inside ProfileDocumentsForm, a sibling Livewire component
 * that updates over its own AJAX round trip — the surrounding static page
 * around it is never touched. Without this component, a learner who uploads
 * a photo would see it appear in "Upload documents" immediately but the
 * initials disc at the top of the page would keep showing until they
 * reloaded, which reads as the upload not having worked.
 *
 * profile-photo-updated is dispatched by ProfileDocumentsForm after every
 * upload AND every removal (App\Livewire\Student\ProfileDocumentsForm). This
 * component's only job on hearing it is to re-render — Auth::user() is read
 * fresh in render() regardless, so simply reacting to the event is enough;
 * there is no state here to update by hand.
 */
final class ProfileIdentity extends Component
{
    #[On('profile-photo-updated')]
    public function refresh(): void
    {
        // Intentionally empty. Any Livewire update re-runs render() with a
        // fresh Auth::user() read — this method exists only so the component
        // reacts to the event at all.
    }

    public function render(): View
    {
        return view('livewire.student.profile-identity', [
            'user' => $this->user(),
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
