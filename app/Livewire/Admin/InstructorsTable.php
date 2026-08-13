<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\UserRole;
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

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function rows(): LengthAwarePaginator
    {
        return $this->applySort(
            $this->applySearch(User::query()->role(UserRole::Instructor), ['name', 'email']),
            default: 'name',
            defaultDirection: 'asc',
        )->paginate($this->perPage);
    }

    public function render(): View
    {
        return view('livewire.admin.instructors-table', [
            'instructors' => $this->rows(),
        ]);
    }
}
