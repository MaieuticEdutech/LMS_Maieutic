<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The read-only identity card at the top of the profile page — initials,
 * name, email, join date, role and status.
 */
final class ProfileIdentity extends Component
{
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
