<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Assessments;

use App\Livewire\Concerns\WithAdminTable;
use App\Models\Assessment;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Platform-wide assessment list (phases.md Phase 8, Admin\AssessmentsTable).
 * Every quiz and test across every course, regardless of attach point —
 * the per-lesson/module/course authoring entry points create rows here, this
 * screen is the admin's overview of all of them.
 */
#[Layout('layouts.admin', [
    'breadcrumbs' => [
        ['label' => 'Administration', 'url' => '/admin'],
        ['label' => 'Assessments', 'url' => null],
    ],
])]
final class AssessmentsTable extends Component
{
    use WithAdminTable;
    use WithPagination;

    public string $typeFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Assessment::class);
    }

    /**
     * @return list<string>
     */
    protected function filterProperties(): array
    {
        return ['typeFilter'];
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Assessment>
     */
    public function rows(): LengthAwarePaginator
    {
        $query = Assessment::query()->with('assessable');

        $query = $this->applySort(
            $this->applySearch($query, ['title']),
            default: 'created_at',
            defaultDirection: 'desc',
        );

        if ($this->typeFilter !== '') {
            $query->where('type', $this->typeFilter);
        }

        return $query->paginate($this->perPage);
    }

    /**
     * Human description of what an assessment attaches to, for the list —
     * resolved here rather than on the model, since it is presentation only.
     */
    public function attachPointLabel(Assessment $assessment): string
    {
        $assessable = $assessment->assessable;

        return match (true) {
            $assessable === null => 'Unattached',
            default => class_basename($assessable).': '.($assessable->title ?? $assessable->name ?? '#'.$assessable->getKey()),
        };
    }

    public function render(): View
    {
        return view('livewire.admin.assessments.table', [
            'assessments' => $this->rows(),
        ]);
    }
}
