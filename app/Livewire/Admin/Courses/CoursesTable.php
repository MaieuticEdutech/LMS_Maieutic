<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Courses;

use App\Enums\CourseStatus;
use App\Livewire\Concerns\WithAdminTable;
use App\Models\Course;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Course list (docs/UI-GUIDE.md §3: Admin/Courses/**, Srivathsa). Phase 4
 * shipped this read-only; Phase 5 adds the "Create course" entry point and
 * the per-card links into CourseBuilder — the query/search/filter/paginate
 * logic here is otherwise unchanged from Phase 4 Checkpoint 6.
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

    /**
     * Counts for the header summary line ("22 courses · 18 published · 3
     * draft · 1 archived") — unfiltered, so the tabs' own counts stay stable
     * as the admin filters/searches the list beneath them.
     *
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        return [
            'all' => Course::query()->count(),
            ...Course::query()
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->all(),
        ];
    }

    public function render(): View
    {
        return view('livewire.admin.courses.table', [
            'courses' => $this->rows(),
            'statusOptions' => $this->statusOptions(),
            'statusCounts' => $this->statusCounts(),
        ]);
    }
}
