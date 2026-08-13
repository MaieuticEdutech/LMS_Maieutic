<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\DeleteUser;
use App\Actions\Admin\ForcePasswordReset;
use App\Actions\Admin\SetUserStatus;
use App\Actions\Identity\SendActivationLink;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', [
    'breadcrumbs' => [
        ['label' => 'Administration', 'url' => '/admin'],
        ['label' => 'Instructors', 'url' => '/admin/instructors'],
        ['label' => 'Detail', 'url' => null],
    ],
])]
final class InstructorDetail extends Component
{
    public User $instructor;

    public function mount(User $instructor): void
    {
        abort_unless($instructor->hasRole(UserRole::Instructor), 404);
        $this->authorize('view', $instructor);

        $this->instructor = $instructor;
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    public function changeStatus(string $status, SetUserStatus $setUserStatus): void
    {
        $this->authorize('changeStatus', $this->instructor);

        $setUserStatus->handle($this->instructor, UserStatus::from($status), $this->actor());
        $this->instructor->refresh();

        session()->flash('status', "Status updated to {$this->instructor->status->label()}.");
    }

    public function resendActivation(SendActivationLink $sendActivationLink): void
    {
        $this->authorize('update', $this->instructor);
        abort_unless($this->instructor->status === UserStatus::PendingActivation, 422);

        $sendActivationLink->handle($this->instructor);

        session()->flash('status', "Activation link re-sent to {$this->instructor->email}.");
    }

    public function forcePasswordReset(ForcePasswordReset $forcePasswordReset): void
    {
        $this->authorize('update', $this->instructor);

        $forcePasswordReset->handle($this->instructor, $this->actor());

        session()->flash('status', "Password reset link sent to {$this->instructor->email}.");
    }

    public function delete(DeleteUser $deleteUser): mixed
    {
        $this->authorize('delete', $this->instructor);

        $deleteUser->handle($this->instructor, $this->actor());

        session()->flash('status', 'Instructor deleted.');

        return redirect()->route('admin.instructors.index');
    }

    public function render(): View
    {
        // Explicit eager load, done here rather than only in mount(): Livewire
        // rehydrates `$instructor` from scratch on every subsequent request
        // (it does not carry loaded relations across the wire), and render()
        // runs on every one of those requests, whereas mount() runs once.
        // `preventLazyLoading` (AppServiceProvider) throws on a lazy
        // `$instructor->instructorProfile` access outside production — the
        // view reads that relation, so it must be loaded on every render.
        $this->instructor->loadMissing('instructorProfile');

        return view('livewire.admin.instructor-detail', [
            'assignableStatuses' => UserStatus::adminAssignable(),
        ]);
    }
}
