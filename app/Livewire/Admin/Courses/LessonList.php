<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Courses;

use App\Actions\Catalog\CreateLesson;
use App\Actions\Catalog\DeleteLesson;
use App\Actions\Catalog\ReorderLessons;
use App\Enums\LessonType;
use App\Exceptions\ReorderException;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Services\Content\ContentTypeRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The lesson list for one module, nested inside ModuleList
 * (docs/UI-GUIDE.md §3 Admin/Courses/**). Owns lesson create/delete and
 * reorder within this module; editing a lesson's content opens LessonEditor.
 */
final class LessonList extends Component
{
    public Module $module;

    public bool $showForm = false;

    public string $title = '';

    public string $type = '';

    public ?int $deletingLessonId = null;

    public function mount(Module $module): void
    {
        $this->module = $module;
    }

    public function openCreate(): void
    {
        $this->authorize('manageContent', $this->module->course);

        $this->reset(['title', 'type']);
        $this->showForm = true;
    }

    public function save(CreateLesson $create): void
    {
        $this->authorize('manageContent', $this->module->course);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(LessonType::class)->only(
                app(ContentTypeRegistry::class)->selectableTypes(),
            )],
        ]);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $create->handle($this->module, [
            'title' => $validated['title'],
            'type' => LessonType::from($validated['type']),
        ], $actor);

        $this->module->refresh();
        $this->showForm = false;
        $this->reset(['title', 'type']);
        $this->dispatch('lesson-list-changed');
    }

    public function editLesson(int $lessonId): void
    {
        $this->dispatch('open-modal', 'lesson-editor-'.$lessonId);
    }

    public function confirmDelete(int $lessonId): void
    {
        $this->deletingLessonId = $lessonId;
    }

    public function delete(DeleteLesson $delete): void
    {
        if ($this->deletingLessonId === null) {
            return;
        }

        $this->authorize('manageContent', $this->module->course);

        $lesson = $this->module->lessons()->findOrFail($this->deletingLessonId);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $delete->handle($lesson, $actor);
        $this->module->refresh();
        $this->deletingLessonId = null;
        $this->dispatch('lesson-list-changed');
    }

    public function moveLesson(int $lessonId, int $direction): void
    {
        $ids = array_values($this->module->lessons()->pluck('id')->map(fn ($id) => (int) $id)->all());
        $index = array_search($lessonId, $ids, true);

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
        $this->authorize('manageContent', $this->module->course);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        try {
            app(ReorderLessons::class)->handle($this->module, $orderedIds, $actor);
        } catch (ReorderException $e) {
            $this->addError('action', $e->getMessage());
        }

        $this->module->refresh();
    }

    /**
     * @return list<LessonType>
     */
    public function selectableTypes(): array
    {
        return app(ContentTypeRegistry::class)->selectableTypes();
    }

    /**
     * @return Collection<int, Lesson>
     */
    public function lessons(): Collection
    {
        return $this->module->lessons()->get();
    }

    public function render(): View
    {
        return view('livewire.admin.courses.lesson-list', [
            'lessons' => $this->lessons(),
            'selectableTypes' => $this->selectableTypes(),
        ]);
    }
}
