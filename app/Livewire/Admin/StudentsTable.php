<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Livewire\Concerns\WithAdminTable;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin', [
    'breadcrumbs' => [
        ['label' => 'Administration', 'url' => '/admin'],
        ['label' => 'Students', 'url' => null],
    ],
])]
final class StudentsTable extends Component
{
    use WithAdminTable;
    use WithPagination;

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    /**
     * @return list<string>
     */
    protected function filterProperties(): array
    {
        return ['statusFilter'];
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function rows(): LengthAwarePaginator
    {
        $query = User::query()->role(UserRole::Student);

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        return $this->applySort(
            $this->applySearch($query, ['name', 'email']),
            default: 'name',
            defaultDirection: 'asc',
        )->paginate($this->perPage);
    }

    /**
     * Every lifecycle state, so an administrator can find the accounts that
     * need attention — the suspended, and the ones stuck awaiting activation
     * or verification, which are invisible in a list sorted by name.
     *
     * @return list<UserStatus>
     */
    public function statusOptions(): array
    {
        return UserStatus::cases();
    }

    public function render(): View
    {
        return view('livewire.admin.students-table', [
            'students' => $this->rows(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }
}
