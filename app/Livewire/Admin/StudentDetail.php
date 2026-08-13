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
        ['label' => 'Students', 'url' => '/admin/students'],
        ['label' => 'Detail', 'url' => null],
    ],
])]
final class StudentDetail extends Component
{
    public User $student;

    public function mount(User $student): void
    {
        abort_unless($student->hasRole(UserRole::Student), 404);
        $this->authorize('view', $student);

        $this->student = $student;
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    public function changeStatus(string $status, SetUserStatus $setUserStatus): void
    {
        $this->authorize('changeStatus', $this->student);

        $setUserStatus->handle($this->student, UserStatus::from($status), $this->actor());
        $this->student->refresh();

        session()->flash('status', "Status updated to {$this->student->status->label()}.");
    }

    public function resendActivation(SendActivationLink $sendActivationLink): void
    {
        $this->authorize('update', $this->student);
        abort_unless($this->student->status === UserStatus::PendingActivation, 422);

        $sendActivationLink->handle($this->student);

        session()->flash('status', "Activation link re-sent to {$this->student->email}.");
    }

    public function forcePasswordReset(ForcePasswordReset $forcePasswordReset): void
    {
        $this->authorize('update', $this->student);

        $forcePasswordReset->handle($this->student, $this->actor());

        session()->flash('status', "Password reset link sent to {$this->student->email}.");
    }

    public function delete(DeleteUser $deleteUser): mixed
    {
        $this->authorize('delete', $this->student);

        $deleteUser->handle($this->student, $this->actor());

        session()->flash('status', 'Student deleted.');

        return redirect()->route('admin.students.index');
    }

    public function render(): View
    {
        return view('livewire.admin.student-detail', [
            'assignableStatuses' => UserStatus::adminAssignable(),
        ]);
    }
}
