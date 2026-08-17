<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * The reusable admin table pattern named in phases.md's Phase 4 feature list
 * (search, filter, sort, paginate, export hook). Every admin listing screen
 * (`StudentsTable`, `InstructorsTable`, `CoursesTable`, `AuditLogTable`, ...)
 * composes this instead of reimplementing search/sort/pagination — a second
 * implementation of this pattern is a defect, the same rule the shared Blade
 * component library already lives by (TRACK-B-SRIVATHSA.md).
 *
 * REQUIRES the consuming component to also `use Livewire\WithPagination`.
 * Not bundled here so a component can choose simple vs. cursor pagination —
 * but every admin table MUST paginate server-side (NFR-PERF-02); there is no
 * "show all" mode anywhere in this system.
 *
 * `applySearch()`/`applySort()` are public, not protected: they are meant to
 * be called from the consuming component's own query-building method, and
 * making them public keeps this trait testable in isolation without standing
 * up a full Livewire render cycle for logic that has nothing to do with
 * rendering.
 */
trait WithAdminTable
{
    #[Url(as: 'q', history: false)]
    public string $search = '';

    public ?string $sortField = null;

    /**
     * @var 'asc'|'desc'
     */
    public string $sortDirection = 'asc';

    public int $perPage = 15;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Toggle direction on a repeat click of the same column; otherwise sort
     * the new column ascending.
     */
    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    /**
     * The names of this table's own filter properties.
     *
     * Filters are declared as typed properties on each table rather than
     * shared through one untyped array here, because no two admin tables
     * filter on the same thing — status, source, course, type, action — and a
     * generic `array $filters` would give up both the type and the ability to
     * bind an individual filter to the URL. This hook is what lets the shared
     * reset still know what to clear.
     *
     * @return list<string>
     */
    protected function filterProperties(): array
    {
        return [];
    }

    /**
     * Clear search, sort AND the table's own filters.
     *
     * The filter names come from filterProperties() because this used to reset
     * a `$filters` array that no table ever populated: the Enrolments screen's
     * "Clear filters" button cleared the search box and left status, source
     * and course exactly as they were. A reset that visibly resets nothing is
     * worse than no button at all.
     */
    public function resetTableFilters(): void
    {
        $this->reset(array_merge(
            ['search', 'sortField', 'sortDirection'],
            $this->filterProperties(),
        ));

        $this->resetPage();
    }

    /**
     * The export hook (phases.md: "...export hook"). No exporter exists yet
     * — Phase 13 (Reporting) is where a real implementation lands. This
     * dispatches a browser event so a concrete table can wire a listener
     * once one exists, rather than every table inventing its own export
     * button wiring in the meantime.
     *
     * No payload is passed: `component` is a reserved Livewire dispatch
     * parameter (used for event routing, silently stripped from the
     * general payload if you try to use it for anything else — found this
     * the hard way), and Livewire already records which component
     * dispatched an event without needing it repeated as a param.
     */
    public function requestExport(): void
    {
        $this->dispatch('admin-table-export-requested');
    }

    /**
     * @param  Builder<*>  $query
     * @param  list<string>  $columns
     * @return Builder<*>
     */
    public function applySearch(Builder $query, array $columns): Builder
    {
        if ($this->search === '' || $columns === []) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($columns): void {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', '%'.$this->search.'%');
            }
        });
    }

    /**
     * @param  Builder<*>  $query
     * @param  'asc'|'desc'  $defaultDirection
     * @return Builder<*>
     */
    public function applySort(Builder $query, string $default = 'id', string $defaultDirection = 'desc'): Builder
    {
        if ($this->sortField === null) {
            return $query->orderBy($default, $defaultDirection);
        }

        return $query->orderBy($this->sortField, $this->sortDirection);
    }
}
