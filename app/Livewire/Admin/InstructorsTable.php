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
        ['label' => 'Instructors', 'url' => null],
    ],
])]
final class InstructorsTable extends Component
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
        $query = User::query()->role(UserRole::Instructor);

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
     * @return list<UserStatus>
     */
    public function statusOptions(): array
    {
        return UserStatus::cases();
    }

    public function render(): View
    {
        return view('livewire.admin.instructors-table', [
            'instructors' => $this->rows(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }
}
