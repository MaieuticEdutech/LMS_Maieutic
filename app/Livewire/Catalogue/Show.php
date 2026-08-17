<?php

declare(strict_types=1);

namespace App\Livewire\Catalogue;

use App\Models\Course;
use App\Models\CourseReview;
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

        $this->course = $course;
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

    /**
     * How many people have enrolled (design handoff §3 — "12,480 learners").
     *
     * ═════════════════════════════════════════════════════════════════════
     * COUNTED, NOT CACHED, AND IT COUNTS EVERY ENROLMENT EVER GRANTED.
     *
     * Deliberately not filtered to currently-active access. "12,480 learners"
     * is a claim about how many people have taken the course, not about how
     * many can open it this minute — excluding someone whose year of access
     * expired would make the figure shrink over time, which is both wrong and
     * exactly the kind of number that gets queried by whoever notices.
     *
     * One COUNT on one page, rather than a counter cache: this is the only
     * place it appears, and a cached column would be a third thing
     * GrantEnrollment had to keep in step for no benefit.
     * ═════════════════════════════════════════════════════════════════════
     */
    public function learnerCount(): int
    {
        return $this->course->enrollments()->count();
    }

    /**
     * The published reviews, newest first — the words behind the star.
     *
     * @return Collection<int, CourseReview>
     */
    public function reviews(): Collection
    {
        return $this->course->reviews()
            ->whereNotNull('body')
            ->with('user')
            ->latest()
            ->limit(5)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.catalogue.show', [
            'curriculum' => $this->curriculum(),
            'learners' => $this->learnerCount(),
            'reviews' => $this->reviews(),
        ]);
    }
}
