<?php

declare(strict_types=1);

namespace App\Livewire\Catalogue;

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
