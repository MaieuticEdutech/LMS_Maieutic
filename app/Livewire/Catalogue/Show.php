<?php

declare(strict_types=1);

namespace App\Livewire\Catalogue;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Public course detail page (docs/UI-GUIDE.md §3 Catalogue/**, AC-01).
 *
 * METADATA ONLY, adapted from the reference for V1's no-preview rule
 * (ADR-014): every lesson row shows title + duration and nothing else —
 * there is no "free preview" lesson in this version, so no lesson row is
 * ever rendered differently from any other. No lesson body, media relation,
 * MediaFile URL or assessment reference is ever passed to this view.
 */
#[Layout('layouts.public')]
final class Show extends Component
{
    public Course $course;

    public function mount(Course $course): void
    {
        $this->authorize('view', $course);

        // thumbnail and category are read by the hero and the card partial;
        // preventLazyLoading() throws on either if they are left unloaded.
        $this->course = $course->loadMissing(['category', 'thumbnail']);
    }

    /**
     * Published modules with their published lessons only (FR-CNT-05) —
     * titles and durations, nothing else.
     *
     * @return Collection<int, Module>
     */
    public function curriculum(): Collection
    {
        return $this->course->modules()
            ->where('is_published', true)
            ->with(['lessons' => fn ($q) => $q->where('is_published', true)])
            ->get();
    }

    public function render(): View
    {
        return view('livewire.catalogue.show', [
            'curriculum' => $this->curriculum(),
        ]);
    }
}
