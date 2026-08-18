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

    /*
    |--------------------------------------------------------------------------
    | The filter rail
    |--------------------------------------------------------------------------
    |
    | ═════════════════════════════════════════════════════════════════════
    | CHECKBOXES, SO THE FILTERS ARE GENUINELY MULTI-SELECT.
    |
    | The design draws every facet as a checkbox, and a checkbox that behaved
    | like a radio would be the worst of both: a learner ticks "Beginner" and
    | "Intermediate", watches the first one silently clear, and concludes the
    | page is broken. So each facet is an ARRAY and the query ORs within a group
    | while ANDing across groups — which is what anyone who has used a shop
    | expects, and the only reading of a checkbox that is not a lie.
    |
    | Ticking nothing in a group means "no constraint from this group", not
    | "match nothing".
    | ═════════════════════════════════════════════════════════════════════
    */

    /**
     * Selected category slugs.
     *
     * @var array<array-key, mixed>
     */
    #[Url(as: 'category')]
    public array $category = [];

    /**
     * Selected CourseLevel values.
     *
     * @var array<array-key, mixed>
     */
    #[Url(as: 'level')]
    public array $level = [];

    /**
     * Selected duration bands — any of 'short', 'medium', 'long'.
     *
     * The bands match the labels in the prototype's own data — under 10 hours,
     * 10–20, 20+ — measured against `total_duration_seconds`, which the course
     * row already caches.
     *
     * @var array<array-key, mixed>
     */
    #[Url(as: 'duration')]
    public array $duration = [];

    /**
     * Selected minimum-rating bands.
     *
     * Real now that course_reviews exists. Several may be ticked; the LOWEST
     * wins, because "4.5 & up" and "4.0 & up" together can only sensibly mean
     * "4.0 and up" — the bands nest rather than sitting side by side.
     *
     * @var array<array-key, mixed>
     */
    #[Url(as: 'rating')]
    public array $rating = [];

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
        $query = Course::published()->with(['category', 'thumbnail']);

        if ($this->search !== '') {
            $query->where('title', 'like', '%'.$this->search.'%');
        }

        // OR within the group, AND across groups. An empty group constrains
        // nothing rather than matching nothing.
        $categories = array_values(array_filter($this->category, 'is_string'));

        if ($categories !== []) {
            $query->whereHas('category', fn (Builder $q) => $q->whereIn('slug', $categories));
        }

        /*
         * Matched against the enum rather than passed through: a hand-typed
         * ?level[]=anything must narrow to nothing recognised rather than reach
         * the query. Filtering through tryFrom leaves only real cases, and an
         * array of junk therefore behaves like no selection at all.
         */
        $levels = array_values(array_filter(
            array_map(
                static fn (mixed $value): ?CourseLevel => is_string($value) ? CourseLevel::tryFrom($value) : null,
                $this->level,
            ),
        ));

        if ($levels !== []) {
            $query->whereIn('level', array_map(static fn (CourseLevel $l): string => $l->value, $levels));
        }

        /*
         * Bands are looked up rather than parsed, so an unrecognised
         * ?duration[]= is dropped instead of reaching the query. The upper bound
         * is exclusive so a course of exactly 10 hours lands in one band and not
         * two.
         *
         * Several ticked bands OR together inside one nested group — without the
         * nesting the ORs would escape and cancel every AND before them,
         * quietly widening the whole result set rather than narrowing it. That
         * is the classic Eloquent filter bug and it fails silently.
         */
        $bands = array_values(array_filter(array_map(
            fn (mixed $key): ?array => is_string($key) ? ($this->durationBands()[$key] ?? null) : null,
            $this->duration,
        )));

        if ($bands !== []) {
            $query->where(function (Builder $group) use ($bands): void {
                foreach ($bands as $band) {
                    $group->orWhere(function (Builder $one) use ($band): void {
                        $one->where('total_duration_seconds', '>=', $band['min']);

                        if ($band['max'] !== null) {
                            $one->where('total_duration_seconds', '<', $band['max']);
                        }
                    });
                }
            });
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
        $thresholds = array_values(array_filter(
            $this->rating,
            fn (mixed $value): bool => is_string($value) && isset($this->ratingBands()[$value]),
        ));

        if ($thresholds !== []) {
            // The LOWEST ticked band wins: "4.5 & up" and "4.0 & up" together
            // can only sensibly mean "4.0 and up", because the bands nest.
            $tenths = (int) round(min(array_map('floatval', $thresholds)) * 10);

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
