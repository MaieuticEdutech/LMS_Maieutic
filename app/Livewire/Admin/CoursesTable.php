<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\CourseStatus;
use App\Livewire\Concerns\WithAdminTable;
use App\Models\Course;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * READ-ONLY course list (phases.md Phase 4: "Course list with status, search
 * and filters (CRUD arrives in Phase 5)"). No create/edit/delete UI and no
 * row-level links — those routes belong to Phase 5's Course Builder and do
 * not exist yet.
 *
 * Authorisation nuance: CoursePolicy::viewAny() is deliberately `true` for
 * everyone — it is the public catalogue browse permission, not an admin
 * gate. The real protection for THIS screen is routes/admin.php's
 * ['auth', 'active', 'role:super_admin'] middleware group. The authorize()
 * call below is kept anyway for consistency with every other admin
 * component, but it is not, by itself, what keeps a non-super-admin out.
 */
#[Layout('layouts.admin', [
    'breadcrumbs' => [
        ['label' => 'Administration', 'url' => '/admin'],
        ['label' => 'Courses', 'url' => null],
    ],
])]
final class CoursesTable extends Component
{
    use WithAdminTable;
    use WithPagination;

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Course::class);
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Course>
     */
    public function rows(): LengthAwarePaginator
    {
        $query = Course::query()->with('category:id,name');

        $query = $this->applySort(
            $this->applySearch($query, ['title']),
            default: 'created_at',
            defaultDirection: 'desc',
        );

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        return $query->paginate($this->perPage);
    }

    /**
     * @return list<CourseStatus>
     */
    public function statusOptions(): array
    {
        return CourseStatus::cases();
    }

    public function render(): View
    {
        return view('livewire.admin.courses-table', [
            'courses' => $this->rows(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }
}
