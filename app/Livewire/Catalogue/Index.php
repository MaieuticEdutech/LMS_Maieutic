<?php

declare(strict_types=1);

namespace App\Livewire\Catalogue;

use App\Enums\CourseLevel;
use App\Models\Category;
use App\Models\Course;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Public course catalogue (docs/UI-GUIDE.md §3 Catalogue/**, phases.md Phase
 * 5). Guests and any authenticated user may browse it — CoursePolicy::viewAny()
 * is deliberately public (AC-01). Renders METADATA ONLY, drawn from
 * Course::published(); no lesson content is queried here at all.
 */
#[Layout('layouts.public')]
final class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'category')]
    public string $category = '';

    /**
     * Difficulty filter — one of CourseLevel's values, or '' for any.
     */
    #[Url(as: 'level')]
    public string $level = '';

    /**
     * Duration band — 'short', 'medium', 'long', or '' for any.
     *
     * The handoff's rail offers CATEGORY, LEVEL, DURATION and RATING. Three are
     * filterable against something this system records; RATING is not, because
     * there is no rating table anywhere in the schema. A facet that quietly
     * does nothing is worse than its absence, so it is omitted rather than
     * drawn inert.
     *
     * The bands match the labels in the prototype's own data — under 10 hours,
     * 10–20, 20+ — measured against `total_duration_seconds`, which the course
     * row already caches.
     */
    #[Url(as: 'duration')]
    public string $duration = '';

    /**
     * Minimum mean rating — '4.5', '4.0', or '' for any.
     *
     * The handoff's fourth facet, real now that course_reviews exists. Compared
     * against the cached sum and count rather than a stored average, because
     * there is no stored average — see the migration for why.
     */
    #[Url(as: 'rating')]
    public string $rating = '';

    /**
     * @return array<string, string>
     */
    public function ratingBands(): array
    {
        return [
            '4.5' => '4.5 & up',
            '4.0' => '4.0 & up',
            '3.0' => '3.0 & up',
        ];
    }

    /**
     * Boundaries in seconds, so the query and the labels cannot drift apart.
     *
     * @return array<string, array{label: string, min: int, max: int|null}>
     */
    public function durationBands(): array
    {
        return [
            'short' => ['label' => 'Under 10 hours', 'min' => 0, 'max' => 10 * 3600],
            'medium' => ['label' => '10–20 hours', 'min' => 10 * 3600, 'max' => 20 * 3600],
            'long' => ['label' => '20+ hours', 'min' => 20 * 3600, 'max' => null],
        ];
    }

    #[Url]
    public string $sort = 'newest';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    public function updatingLevel(): void
    {
        $this->resetPage();
    }

    public function updatingDuration(): void
    {
        $this->resetPage();
    }

    public function updatingRating(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Course>
     */
    public function courses(): LengthAwarePaginator
    {
        $query = Course::published()->with('category');

        if ($this->search !== '') {
            $query->where('title', 'like', '%'.$this->search.'%');
        }

        if ($this->category !== '') {
            $query->whereHas('category', fn (Builder $q) => $q->where('slug', $this->category));
        }

        // Matched against the enum rather than passed through: a hand-typed
        // ?level=anything must narrow to nothing recognised rather than reach
        // the query. CourseLevel::tryFrom returns null for junk.
        if ($this->level !== '' && CourseLevel::tryFrom($this->level) instanceof CourseLevel) {
            $query->where('level', $this->level);
        }

        // Bands are looked up rather than parsed, so an unrecognised ?duration=
        // narrows to nothing recognised instead of reaching the query. The upper
        // bound is exclusive so a course of exactly 10 hours lands in one band
        // and not two.
        $band = $this->durationBands()[$this->duration] ?? null;

        if ($band !== null) {
            $query->where('total_duration_seconds', '>=', $band['min']);

            if ($band['max'] !== null) {
                $query->where('total_duration_seconds', '<', $band['max']);
            }
        }

        /*
         * ═════════════════════════════════════════════════════════════════
         * INTEGER ARITHMETIC ONLY — NO FLOAT REACHES THE QUERY.
         *
         * "4.5 and up" is `sum / count >= 4.5`, which becomes
         * `sum * 10 >= 45 * count` once both sides are multiplied by ten. The
         * threshold is carried in TENTHS, so the comparison is exact and the
         * database never divides.
         *
         * The obvious version — binding 4.5 against `rating_sum >= ? * count`
         * — is rejected outright by PostgreSQL, which infers the parameter type
         * from the integer column beside it. Which is the database being right:
         * this schema deliberately holds no floats (see the migration), and the
         * query should not introduce one.
         *
         * An unrated course is excluded explicitly. Without that, `0 >= 45 * 0`
         * is TRUE and every brand-new course would appear under "4.5 & up" —
         * unrated is not a high rating.
         * ═════════════════════════════════════════════════════════════════
         */
        if (isset($this->ratingBands()[$this->rating])) {
            $tenths = (int) round(((float) $this->rating) * 10);

            $query->where('rating_count', '>', 0)
                ->whereRaw('rating_sum * 10 >= ? * rating_count', [$tenths]);
        }

        match ($this->sort) {
            'price-asc' => $query->orderBy('price_amount'),
            'price-desc' => $query->orderByDesc('price_amount'),
            default => $query->orderByDesc('published_at'),
        };

        return $query->paginate(12);
    }

    /**
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        return Category::query()->roots()->ordered()->get();
    }

    public function render(): View
    {
        return view('livewire.catalogue.index', [
            'courses' => $this->courses(),
            'categories' => $this->categories(),
        ]);
    }
}
