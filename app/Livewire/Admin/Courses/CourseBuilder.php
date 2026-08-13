<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Courses;

use App\Actions\Catalog\ArchiveCourse;
use App\Actions\Catalog\CreateCourse;
use App\Actions\Catalog\DeleteCourse;
use App\Actions\Catalog\PublishCourse;
use App\Actions\Catalog\UnpublishCourse;
use App\Actions\Catalog\UpdateCourse;
use App\Enums\CourseLevel;
use App\Exceptions\CourseDeletionException;
use App\Exceptions\CoursePublishException;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use App\Services\Content\CoursePublishValidator;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The Course Builder (docs/UI-GUIDE.md §3, phases.md Phase 5, architecture.md
 * §9.3 "one Livewire-driven single screen").
 *
 * Combined create/edit + structure screen — there is no separate CourseForm.
 * The left panel below (this class) is the meta form; the right panel is the
 * module/lesson tree, owned by the nested ModuleList component. "Create
 * course" saves a minimal draft here and redirects into this same screen for
 * the new course, exactly mirroring InstructorForm's create-or-edit pattern
 * one level up.
 */
#[Layout('layouts.admin')]
final class CourseBuilder extends Component
{
    public ?Course $course = null;

    public string $title = '';

    public ?string $subtitle = null;

    public ?string $description = null;

    public ?int $category_id = null;

    public string $level = 'beginner';

    public string $language = 'en';

    public string $priceRupees = '';

    public bool $requires_final_test = false;

    /** @var list<string> */
    public array $outcomes = [''];

    /** @var list<string> */
    public array $requirements = [''];

    public bool $confirmingDelete = false;

    public function mount(?Course $course = null): void
    {
        if ($course !== null) {
            $this->authorize('update', $course);

            $this->course = $course;
            $this->title = $course->title;
            $this->subtitle = $course->subtitle;
            $this->description = $course->description;
            $this->category_id = $course->category_id;
            $this->level = $course->level->value;
            $this->language = $course->language;
            $this->priceRupees = $course->price_amount > 0
                ? (string) Money::fromMinor($course->price_amount, $course->currency)->toMajor()
                : '';
            $this->requires_final_test = $course->requires_final_test;
            $this->outcomes = $course->outcomes !== null && $course->outcomes !== [] ? $course->outcomes : [''];
            $this->requirements = $course->requirements !== null && $course->requirements !== [] ? $course->requirements : [''];

            return;
        }

        $this->authorize('create', Course::class);
    }

    public function addOutcome(): void
    {
        $this->outcomes[] = '';
    }

    public function removeOutcome(int $index): void
    {
        $remaining = array_values(array_filter(
            $this->outcomes,
            static fn (int $i): bool => $i !== $index,
            ARRAY_FILTER_USE_KEY,
        ));

        $this->outcomes = $remaining === [] ? [''] : $remaining;
    }

    public function addRequirement(): void
    {
        $this->requirements[] = '';
    }

    public function removeRequirement(int $index): void
    {
        $remaining = array_values(array_filter(
            $this->requirements,
            static fn (int $i): bool => $i !== $index,
            ARRAY_FILTER_USE_KEY,
        ));

        $this->requirements = $remaining === [] ? [''] : $remaining;
    }

    public function save(CreateCourse $create, UpdateCourse $update): mixed
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'level' => ['required', Rule::enum(CourseLevel::class)],
            'language' => ['required', 'string', 'max:10'],
            'priceRupees' => ['required', 'numeric', 'min:0.01'],
            'requires_final_test' => ['boolean'],
            'outcomes.*' => ['nullable', 'string', 'max:255'],
            'requirements.*' => ['nullable', 'string', 'max:255'],
        ]);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $attributes = [
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'] ?? null,
            'description' => $validated['description'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'level' => CourseLevel::from($validated['level']),
            'language' => $validated['language'],
            'requires_final_test' => $validated['requires_final_test'] ?? false,
            'outcomes' => $this->cleanList($this->outcomes),
            'requirements' => $this->cleanList($this->requirements),
        ];

        $priceAmount = Money::fromMajor($validated['priceRupees'])->amount;

        if ($this->course === null) {
            $course = $create->handle(['price_amount' => $priceAmount, ...$attributes], $actor);

            session()->flash('status', "Course \"{$course->title}\" created as a draft.");

            return redirect()->route('admin.courses.builder', $course);
        }

        // Only pass price_amount when it actually changed (FR-CRS-11) — a
        // present key is treated by UpdateCourse as an explicit intent to
        // change the price, so a no-op resubmission must not include it.
        if ($priceAmount !== $this->course->price_amount) {
            $attributes['price_amount'] = $priceAmount;
        }

        $update->handle($this->course, $attributes, $actor);
        $this->course->refresh();

        session()->flash('status', "Course \"{$this->course->title}\" saved.");

        return null;
    }

    public function publish(CoursePublishValidator $validator, PublishCourse $publish): void
    {
        if ($this->course === null) {
            return;
        }

        $this->authorize('publish', $this->course);

        if ($validator->blockers($this->course) !== []) {
            return;
        }

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        try {
            $publish->handle($this->course, $actor);
            $this->course->refresh();
            session()->flash('status', "Course \"{$this->course->title}\" published.");
        } catch (CoursePublishException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function unpublish(UnpublishCourse $unpublish): void
    {
        if ($this->course === null) {
            return;
        }

        $this->authorize('publish', $this->course);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $unpublish->handle($this->course, $actor);
        $this->course->refresh();
        session()->flash('status', "Course \"{$this->course->title}\" unpublished. Enrolled students keep their access.");
    }

    public function archive(ArchiveCourse $archive): void
    {
        if ($this->course === null) {
            return;
        }

        $this->authorize('publish', $this->course);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $archive->handle($this->course, $actor);
        $this->course->refresh();
        session()->flash('status', "Course \"{$this->course->title}\" archived.");
    }

    public function delete(DeleteCourse $delete): mixed
    {
        if ($this->course === null) {
            return null;
        }

        $this->authorize('delete', $this->course);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        try {
            $delete->handle($this->course, $actor);

            return redirect()->route('admin.courses.index');
        } catch (CourseDeletionException $e) {
            session()->flash('error', $e->getMessage());
            $this->confirmingDelete = false;

            return null;
        }
    }

    /**
     * The live publish checklist — the same authority PublishCourse enforces,
     * rendered rather than duplicated (architecture.md §9.3).
     *
     * @return list<string>
     */
    #[Computed]
    public function publishBlockers(): array
    {
        if ($this->course === null) {
            return [];
        }

        return app(CoursePublishValidator::class)->blockers($this->course);
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->ordered()->get();
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function cleanList(array $values): array
    {
        return array_values(array_filter(
            array_map(static fn (string $v): string => trim($v), $values),
            static fn (string $v): bool => $v !== '',
        ));
    }

    /**
     * Re-render when a child component changes the structure.
     *
     * `LessonList` already dispatched `lesson-list-changed` and nothing was
     * listening, so the header kept whatever it had rendered with. Adding a
     * lesson updated the tree — that component re-renders itself — while the
     * summary above it stayed stale until a full page reload.
     */
    #[On('lesson-list-changed')]
    #[On('module-list-changed')]
    public function structureChanged(): void
    {
        // No body needed: receiving the event is what triggers the re-render,
        // and render() recomputes the counts from the database.
    }

    public function render(): View
    {
        /*
         * Counts are loaded HERE rather than in mount(), and this is the whole
         * bug: the header reads `$course->modules_count` / `lessons_count`,
         * which are `withCount` aggregates. Nothing ever loaded them, so both
         * were always absent and rendered as 0 — a course with three modules
         * reported "0 modules · 0 lessons".
         *
         * Loading them in mount() would not have been enough either: every
         * mutation on this component calls `$course->refresh()`, and refresh()
         * reloads the row's own columns while DISCARDING aggregates. They have
         * to be recomputed per render, which is also what makes the count
         * correct immediately after a child adds a lesson.
         */
        $this->course?->loadCount(['modules', 'lessons']);

        return view('livewire.admin.courses.builder');
    }
}
