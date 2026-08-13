<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Courses;

use App\Actions\Catalog\CreateModule;
use App\Actions\Catalog\DeleteModule;
use App\Actions\Catalog\ReorderModules;
use App\Actions\Catalog\UpdateModule;
use App\Exceptions\ReorderException;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The module list inside the Course Builder's structure panel
 * (docs/UI-GUIDE.md §3 Admin/Courses/**). Owns module create/edit/delete and
 * reorder; each module nests a LessonList for its own lessons.
 */
final class ModuleList extends Component
{
    public Course $course;

    public bool $showForm = false;

    public ?Module $editing = null;

    public string $title = '';

    public ?string $description = null;

    public bool $is_published = false;

    public ?int $deletingModuleId = null;

    public function mount(Course $course): void
    {
        $this->course = $course;
    }

    public function openCreate(): void
    {
        $this->authorize('manageContent', $this->course);

        $this->reset(['editing', 'title', 'description', 'is_published']);
        $this->showForm = true;
    }

    public function openEdit(int $moduleId): void
    {
        $this->authorize('manageContent', $this->course);

        $module = $this->course->modules()->findOrFail($moduleId);

        $this->editing = $module;
        $this->title = $module->title;
        $this->description = $module->description;
        $this->is_published = $module->is_published;
        $this->showForm = true;
    }

    public function save(CreateModule $create, UpdateModule $update): void
    {
        $this->authorize('manageContent', $this->course);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['boolean'],
        ]);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        if ($this->editing !== null) {
            $update->handle($this->editing, $validated, $actor);
        } else {
            $create->handle($this->course, $validated, $actor);
        }

        $this->course->refresh();
        $this->showForm = false;
        $this->reset(['editing', 'title', 'description', 'is_published']);

        // Tells CourseBuilder to recount — its header sits outside this
        // component and would otherwise keep showing the old total.
        $this->dispatch('module-list-changed');
    }

    public function confirmDelete(int $moduleId): void
    {
        $this->deletingModuleId = $moduleId;
    }

    public function delete(DeleteModule $delete): void
    {
        if ($this->deletingModuleId === null) {
            return;
        }

        $this->authorize('manageContent', $this->course);

        $module = $this->course->modules()->findOrFail($this->deletingModuleId);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $delete->handle($module, $actor);
        $this->course->refresh();
        $this->deletingModuleId = null;

        // Deleting a module takes its lessons with it, so both counts move.
        $this->dispatch('module-list-changed');
    }

    /**
     * Move one module up (-1) or down (+1) — the keyboard-operable reorder
     * control (docs/UI-GUIDE.md §12). The drag handle in the view calls this
     * same method with a fully recomputed order.
     */
    public function moveModule(int $moduleId, int $direction): void
    {
        $ids = array_values($this->course->modules()->pluck('id')->map(fn ($id) => (int) $id)->all());
        $index = array_search($moduleId, $ids, true);

        if ($index === false) {
            return;
        }

        $index = (int) $index;
        $target = $index + $direction;

        if ($target < 0 || $target >= count($ids)) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        $this->reorder(array_values($ids));
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function reorder(array $orderedIds): void
    {
        $this->authorize('manageContent', $this->course);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        try {
            app(ReorderModules::class)->handle($this->course, $orderedIds, $actor);
        } catch (ReorderException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->course->refresh();
    }

    #[On('lesson-list-changed')]
    public function refreshCourse(): void
    {
        $this->course->refresh();
    }

    /**
     * @return Collection<int, Module>
     */
    public function modules(): Collection
    {
        return $this->course->modules()->withCount('lessons')->get();
    }

    public function render(): View
    {
        return view('livewire.admin.courses.module-list', [
            'modules' => $this->modules(),
        ]);
    }
}
