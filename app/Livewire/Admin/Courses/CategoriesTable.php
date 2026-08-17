<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Courses;

use App\Actions\Catalog\CreateCategory;
use App\Actions\Catalog\DeleteCategory;
use App\Actions\Catalog\UpdateCategory;
use App\Models\Category;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Category management (FR-CNT-15).
 *
 * The Course Builder has always offered a category field, but the table could
 * only ever be populated through tinker — no route, no component, no seeder —
 * so the dropdown was permanently empty on a fresh database and the column was
 * effectively dead. This is the screen that was missing.
 *
 * Modal create/edit and a confirm-before-delete, mirroring ModuleList rather
 * than inventing a second admin-CRUD shape.
 *
 * A FLAT LIST, NOT A TREE WIDGET. The hierarchy is one level deep in practice
 * and the parent is chosen from a dropdown; rendering a drag-and-drop tree
 * would be a lot of machinery for a taxonomy that is edited a few times a
 * year. Children are shown indented under their parent so the shape is still
 * legible.
 */
#[Layout('layouts.admin', [
    'breadcrumbs' => [
        ['label' => 'Administration', 'url' => '/admin'],
        ['label' => 'Categories', 'url' => null],
    ],
])]
final class CategoriesTable extends Component
{
    public bool $showForm = false;

    public ?Category $editing = null;

    public string $name = '';

    public ?string $description = null;

    public ?int $parent_id = null;

    public ?int $deletingCategoryId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Category::class);
    }

    public function openCreate(): void
    {
        $this->authorize('create', Category::class);

        $this->reset(['editing', 'name', 'description', 'parent_id']);
        $this->showForm = true;
    }

    public function openEdit(int $categoryId): void
    {
        $category = Category::query()->findOrFail($categoryId);

        $this->authorize('update', $category);

        $this->editing = $category;
        $this->name = $category->name;
        $this->description = $category->description;
        $this->parent_id = $category->parent_id;
        $this->showForm = true;
    }

    public function save(CreateCategory $create, UpdateCategory $update): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id'),
                // Self-parenting is refused here for a clean field error; the
                // action refuses the whole cycle family, including deeper
                // ones this rule cannot see.
                Rule::notIn(array_filter([$this->editing?->getKey()])),
            ],
        ]);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        try {
            if ($this->editing !== null) {
                $this->authorize('update', $this->editing);
                $update->handle($this->editing, $validated, $actor);
            } else {
                $this->authorize('create', Category::class);
                $create->handle($validated, $actor);
            }
        } catch (InvalidArgumentException $e) {
            // A cycle deeper than the notIn rule can catch.
            $this->addError('parent_id', $e->getMessage());

            return;
        }

        $this->showForm = false;
        $this->reset(['editing', 'name', 'description', 'parent_id']);

        session()->flash('status', 'Category saved.');
    }

    public function confirmDelete(int $categoryId): void
    {
        $this->deletingCategoryId = $categoryId;
    }

    public function cancelDelete(): void
    {
        $this->deletingCategoryId = null;
    }

    public function delete(DeleteCategory $delete): void
    {
        if ($this->deletingCategoryId === null) {
            return;
        }

        $category = Category::query()->findOrFail($this->deletingCategoryId);

        $this->authorize('delete', $category);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $delete->handle($category, $actor);

        $this->deletingCategoryId = null;

        session()->flash('status', 'Category deleted.');
    }

    /**
     * The category awaiting confirmation, so the modal can state exactly what
     * deleting it will do rather than asking "are you sure?" about nothing.
     */
    public function deletingCategory(): ?Category
    {
        if ($this->deletingCategoryId === null) {
            return null;
        }

        return Category::query()
            ->withCount(['courses', 'children'])
            ->find($this->deletingCategoryId);
    }

    /**
     * Roots with their children, ordered — the display order of the whole
     * table in one bounded query pair (NFR-PERF-03).
     *
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        return Category::query()
            ->roots()
            ->ordered()
            ->withCount('courses')
            ->with(['children' => fn ($q) => $q->ordered()->withCount('courses')])
            ->get();
    }

    /**
     * Candidate parents: roots only, so the tree stays one level deep, and
     * never the category being edited.
     *
     * @return Collection<int, Category>
     */
    public function parentOptions(): Collection
    {
        return Category::query()
            ->roots()
            ->ordered()
            ->when($this->editing !== null, fn ($q) => $q->whereKeyNot($this->editing?->getKey()))
            ->get();
    }

    public function render(): View
    {
        return view('livewire.admin.courses.categories-table', [
            'categories' => $this->categories(),
            'parentOptions' => $this->parentOptions(),
            'deleting' => $this->deletingCategory(),
        ]);
    }
}
