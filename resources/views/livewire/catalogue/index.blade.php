<div class="mx-auto max-w-content px-6 py-12">
    <p class="eyebrow text-teal-600">Course catalogue</p>
    <h1 class="mt-2.5 text-4xl">Learn by building.</h1>
    <p class="mt-2 max-w-2xl text-base text-neutral-600">
        Real courses, taught through questions — not information dumps.
    </p>

    <div class="mt-8 flex flex-wrap items-center gap-3">
        <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Search courses&hellip;" class="max-w-xs" />

        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                wire:click="$set('category', '')"
                class="rounded-full border px-3 py-1.5 text-xs font-medium {{ $category === '' ? 'border-teal-600 bg-teal-50 text-teal-700' : 'border-neutral-200 text-neutral-600 hover:border-neutral-300' }}"
            >
                All
            </button>
            @foreach ($categories as $cat)
                <button
                    type="button"
                    wire:click="$set('category', '{{ $cat->slug }}')"
                    class="rounded-full border px-3 py-1.5 text-xs font-medium {{ $category === $cat->slug ? 'border-teal-600 bg-teal-50 text-teal-700' : 'border-neutral-200 text-neutral-600 hover:border-neutral-300' }}"
                >
                    {{ $cat->name }}
                </button>
            @endforeach
        </div>

        <x-select wire:model.live="sort" name="sort" class="ml-auto max-w-xs">
            <option value="newest">Newest</option>
            <option value="price-asc">Price: low to high</option>
            <option value="price-desc">Price: high to low</option>
        </x-select>
    </div>

    <div wire:loading.class="opacity-60" class="mt-8 transition-opacity">
        @if ($courses->isEmpty())
            <x-empty-state title="No courses match" description="Try a different search or category." />
        @else
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($courses as $course)
                    <a
                        href="{{ route('catalogue.show', $course) }}"
                        wire:navigate
                        wire:key="course-{{ $course->id }}"
                        class="flex flex-col overflow-hidden rounded-card border border-neutral-200 bg-white transition-shadow hover:border-neutral-300 hover:shadow-md"
                    >
                        <div class="relative h-32 overflow-hidden bg-teal-50">
                            <span class="absolute bottom-3 left-4 font-serif text-4xl text-teal-700/50">
                                {{ Str::substr($course->title, 0, 1) }}
                            </span>
                        </div>
                        <div class="flex flex-1 flex-col gap-2 px-5 py-4">
                            <p class="eyebrow text-teal-600">{{ $course->category?->name ?? 'General' }}</p>
                            <h3 class="text-lg">{{ $course->title }}</h3>
                            @if ($course->subtitle)
                                <p class="line-clamp-2 text-sm text-neutral-500">{{ $course->subtitle }}</p>
                            @endif
                            <div class="mt-auto flex items-center justify-between border-t border-neutral-100 pt-2.5">
                                <span class="text-xs text-neutral-500">{{ $course->lessons_count }} {{ Str::plural('lesson', $course->lessons_count) }}</span>
                                <span class="font-semibold text-neutral-900">{{ $course->price }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $courses->links() }}
            </div>
        @endif
    </div>
</div>
