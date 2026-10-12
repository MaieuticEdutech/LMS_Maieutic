<?php

declare(strict_types=1);

namespace App\Livewire\Catalogue;

use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Module;
use App\Models\User;
use App\Services\Catalogue\Promotion;
use App\Services\Enrollment\EnrollmentAccessService;
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

        // A guest who joins from the promo button comes straight back here.
        if (auth()->guest() && app(Promotion::class)->active()) {
            session()->put('url.intended', route('catalogue.show', ['course' => $course]));
        }

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

    /**
     * Whether the signed-in viewer already holds access, so the promo card can
     * say "Go to course" instead of offering to enrol again. Asked of the one
     * definition of access (rule S-8).
     */
    public function viewerHasAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && app(EnrollmentAccessService::class)->grantsAccess($user, $this->course);
    }

    public function render(): View
    {
        $promotion = app(Promotion::class);

        return view('livewire.catalogue.show', [
            'promoActive' => $promotion->active(),
            'promoEndsAt' => $promotion->endsAt(),
            'viewerHasAccess' => $promotion->active() && $this->viewerHasAccess(),
            'curriculum' => $this->curriculum(),
            'learners' => $this->learnerCount(),
            'reviews' => $this->reviews(),
        ]);
    }
}
