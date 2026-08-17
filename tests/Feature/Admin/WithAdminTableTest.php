<?php

declare(strict_types=1);

use App\Livewire\Concerns\WithAdminTable;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\WithPagination;

/*
|--------------------------------------------------------------------------
| WithAdminTable — the reusable admin table pattern (Phase 4 Checkpoint 1)
|--------------------------------------------------------------------------
|
| No real admin listing screen exists yet (StudentsTable etc. land in later
| checkpoints), so this trait is proven against a throwaway test-only
| component — the same "test-only handler" pattern Track A's
| ContentTypeRegistryTest already uses to prove a reusable pattern is
| genuinely generic, not coupled to its first real consumer.
|
| Split into two layers deliberately:
|   - "logic" tests instantiate TestAdminUsersTable directly with `new`,
|     bypassing Livewire's HTTP-like request cycle, to prove the
|     query-building is correct, fast and in isolation.
|   - "wiring" tests go through Livewire::test() to prove the parts that
|     genuinely need the component lifecycle (page-reset on update, the
|     export event dispatch) actually work end to end.
|
| There is deliberately no separate "bare trait, no Livewire\Component"
| double: resetTableFilters() and requestExport() call reset()/dispatch(),
| genuine Livewire\Component base methods, so anything using this trait
| must be a real component — a bare double would either be unable to
| exercise the full trait or would silently rely on methods it doesn't
| have. TestAdminUsersTable satisfies the trait's whole contract, so it is
| the only test class this file needs, for both layers.
|
*/

/**
 * A minimal real Livewire component — the only test double this file needs.
 */
final class TestAdminUsersTable extends Component
{
    use WithAdminTable;
    use WithPagination;

    /**
     * A filter declared the way every real admin table declares one: a typed
     * property named by filterProperties(), not an entry in a shared array.
     */
    public string $roleFilter = '';

    /**
     * @return list<string>
     */
    protected function filterProperties(): array
    {
        return ['roleFilter'];
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function rows(): LengthAwarePaginator
    {
        return $this->applySort($this->applySearch(User::query(), ['name', 'email']))
            ->paginate($this->perPage);
    }

    public function render(): string
    {
        return '<div>@foreach ($this->rows() as $row){{ $row->name }}@endforeach</div>';
    }
}

/*
| LOGIC — query building, no Livewire render cycle.
*/
it('applies search across every given column (name or email)', function (): void {
    User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);
    User::factory()->create(['name' => 'Someone Else', 'email' => 'ada.fan@example.com']);

    $table = new TestAdminUsersTable;
    $table->search = 'ada';

    $results = $table->applySearch(User::query(), ['name', 'email'])->get();

    expect($results->pluck('email')->sort()->values()->all())
        ->toBe(['ada.fan@example.com', 'ada@example.com']);
});

it('does not filter at all when search is empty', function (): void {
    User::factory()->count(3)->create();

    $table = new TestAdminUsersTable;

    expect($table->applySearch(User::query(), ['name', 'email'])->count())->toBe(3);
});

it('does not filter when no searchable columns are given', function (): void {
    User::factory()->count(2)->create();

    $table = new TestAdminUsersTable;
    $table->search = 'anything';

    expect($table->applySearch(User::query(), [])->count())->toBe(2);
});

it('sorts by the default column and direction when none is chosen', function (): void {
    $table = new TestAdminUsersTable;

    $sql = $table->applySort(User::query(), 'id', 'desc')->toSql();

    expect($sql)->toContain('order by "id" desc');
});

it('sorts by the explicitly chosen field and direction', function (): void {
    $table = new TestAdminUsersTable;
    $table->sortField = 'name';
    $table->sortDirection = 'asc';

    $sql = $table->applySort(User::query())->toSql();

    expect($sql)->toContain('order by "name" asc');
});

it('toggles sort direction on a repeat click of the same column', function (): void {
    $table = new TestAdminUsersTable;

    $table->sortBy('name');
    expect($table->sortField)->toBe('name')->and($table->sortDirection)->toBe('asc');

    $table->sortBy('name');
    expect($table->sortDirection)->toBe('desc');

    $table->sortBy('name');
    expect($table->sortDirection)->toBe('asc');
});

it('resets to ascending when switching to a different column', function (): void {
    $table = new TestAdminUsersTable;

    $table->sortBy('name');
    $table->sortBy('name'); // now desc
    $table->sortBy('email'); // different column

    expect($table->sortField)->toBe('email')
        ->and($table->sortDirection)->toBe('asc');
});

it('resets pagination to page 1 when the search term changes', function (): void {
    $table = new TestAdminUsersTable;
    $table->setPage(4);

    $table->updatingSearch();

    expect($table->getPage())->toBe(1);
});

it('resets pagination to page 1 when a filter changes', function (): void {
    $table = new TestAdminUsersTable;
    $table->setPage(3);

    $table->updatingRoleFilter();

    expect($table->getPage())->toBe(1);
});

/*
| WIRING — through a real Livewire component, for the parts that need one.
| resetTableFilters() and requestExport() both call Livewire\Component base
| methods (reset(), dispatch()) that a bare trait-only double doesn't have —
| by design, per the trait's docblock: it requires a real component.
*/
it('clears search, the tables own filters and sort together via resetTableFilters', function (): void {
    // The filter is reached through filterProperties(). Previously reset
    // cleared a shared `$filters` array that no real table populated, so on
    // the Enrolments screen "Clear filters" cleared the search box and left
    // every actual filter in place.
    Livewire::test(TestAdminUsersTable::class)
        ->set('search', 'something')
        ->set('roleFilter', 'student')
        ->call('sortBy', 'name')
        ->call('gotoPage', 3)
        ->call('resetTableFilters')
        ->assertSet('search', '')
        ->assertSet('roleFilter', '')
        ->assertSet('sortField', null)
        ->assertSet('sortDirection', 'asc')
        ->assertSet('paginators.page', 1);
});
it('filters the rendered list as the search property updates, through Livewire', function (): void {
    User::factory()->create(['name' => 'Ada Lovelace']);
    User::factory()->create(['name' => 'Grace Hopper']);

    Livewire::test(TestAdminUsersTable::class)
        ->assertSee('Ada Lovelace')
        ->assertSee('Grace Hopper')
        ->set('search', 'Ada')
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});

it('dispatches the export hook event', function (): void {
    Livewire::test(TestAdminUsersTable::class)
        ->call('requestExport')
        ->assertDispatched('admin-table-export-requested');
});
