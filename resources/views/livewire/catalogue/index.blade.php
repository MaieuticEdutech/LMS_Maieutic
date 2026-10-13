{{--
    Explore — the public course catalogue (AC-01, phases.md Phase 5).

    The mockup's screen: eyebrow, serif title, then a 240px filter rail beside a
    three-up card grid with a count and a sort control above it.

    ACCESS BOUNDARY: METADATA ONLY. No lesson body, media file, resource or
    assessment is rendered or linked from here, for anyone, enrolled or not
    (ADR-014 — there is no preview exemption in V1).

    The mockup's rail offers CATEGORY, LEVEL, DURATION and RATING. Only the
    first two filter against something this system records — see the component
    for why the other two are absent rather than decorative.
--}}
<div class="mx-auto w-full max-w-content px-5 pb-24 pt-12 lg:px-10">

    <p class="eyebrow text-teal-600">Course catalogue</p>

    <h1 class="mt-2.5 font-serif text-[40px]/[1.1] font-normal tracking-[-0.02em] md:text-[52px]">Courses</h1>

    <p class="mb-6 mt-2 max-w-[60ch] text-base/[1.6] text-neutral-500">
        Explore courses designed to help you build real-world skills.
    </p>

    {{-- Category pills: the same `category` array the filter has always used,
         so multi-select, the URL and the query are unchanged. --}}
    <div class="mb-8 flex flex-wrap gap-2" role="group" aria-label="Category">
        @foreach ($categories as $cat)
            <label class="cursor-pointer" wire:key="cat-{{ $cat->id }}">
                <input type="checkbox" wire:model.live="category" value="{{ $cat->slug }}" class="peer sr-only">
                <span class="inline-flex rounded-full border border-neutral-200 bg-white px-4 py-1.5 text-[13px] font-semibold text-neutral-700 transition-colors hover:border-teal-600 peer-checked:border-teal-600 peer-checked:bg-teal-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-teal-600 peer-focus-visible:ring-offset-2">{{ $cat->name }}</span>
            </label>
        @endforeach
    </div>

    <div class="grid items-start gap-10 lg:grid-cols-[240px_1fr]">

        {{-- ══ FILTER RAIL ══ sticks below the 64px header. --}}
        <aside class="flex flex-col gap-7 rounded-[20px] border border-neutral-200 bg-white p-5 lg:sticky lg:top-[88px]" aria-label="Filter courses">

            {{-- Checkboxes, genuinely multi-select: ticking nothing in a group
                 means "no constraint from this group". Category lives in the
                 pills above. --}}
            <div>
                <h2 class="mb-3 font-mono text-[11px] font-semibold tracking-[0.14em] text-neutral-700">LEVEL</h2>

                <div class="flex flex-col gap-[9px]">
                    @foreach (\App\Enums\CourseLevel::cases() as $case)
                        <label class="flex cursor-pointer items-center gap-[9px] text-sm text-neutral-700" wire:key="level-{{ $case->value }}">
                            <input type="checkbox" wire:model.live="level" value="{{ $case->value }}" class="h-[15px] w-[15px] accent-teal-600">
                            {{ $case->label() }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <h2 class="mb-3 font-mono text-[11px] font-semibold tracking-[0.14em] text-neutral-700">DURATION</h2>

                <div class="flex flex-col gap-[9px]">
                    {{-- Labels come from the same array the query reads, so the
                         band a student picks and the band they get cannot
                         drift. --}}
                    @foreach ($this->durationBands() as $key => $band)
                        <label class="flex cursor-pointer items-center gap-[9px] text-sm text-neutral-700" wire:key="duration-{{ $key }}">
                            <input type="checkbox" wire:model.live="duration" value="{{ $key }}" class="h-[15px] w-[15px] accent-teal-600">
                            {{ $band['label'] }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <h2 class="mb-3 font-mono text-[11px] font-semibold tracking-[0.14em] text-neutral-700">RATING</h2>

                <div class="flex flex-col gap-[9px]">
                    {{-- A course nobody has rated is excluded from every band
                         rather than treated as zero — unrated is not a bad
                         rating. Ticking two bands takes the lower: they nest. --}}
                    @foreach ($this->ratingBands() as $value => $label)
                        <label class="flex cursor-pointer items-center gap-[9px] text-sm text-neutral-700" wire:key="rating-{{ $value }}">
                            <input type="checkbox" wire:model.live="rating" value="{{ $value }}" class="h-[15px] w-[15px] accent-teal-600">
                            <span class="font-semibold text-red-500" aria-hidden="true">★</span>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
        </aside>

        {{-- ══ RESULTS ══ --}}
        <div wire:loading.class="opacity-60" class="transition-opacity">

            <div class="mb-5 flex items-center justify-between gap-4">
                <span class="text-sm text-neutral-500">
                    {{-- The real total, not a rounded one. "Showing 6 of 42"
                         is only useful if both numbers are true. --}}
                    Showing {{ $courses->count() }} of {{ $courses->total() }} {{ Str::plural('course', $courses->total()) }}
                </span>

                <label class="sr-only" for="catalogue-sort">Sort courses</label>
                <select id="catalogue-sort"
                        wire:model.live="sort"
                        class="h-9 rounded-full border border-neutral-200 bg-white px-4 text-[13.5px] font-medium text-neutral-700">
                    <option value="newest">Newest</option>
                    <option value="popular">Most popular</option>
                    <option value="price-asc">Price: low to high</option>
                    <option value="price-desc">Price: high to low</option>
                </select>
            </div>

            @if ($courses->isEmpty())
                <x-empty-state title="No courses match" description="Try a different search, category or level." />
            @else
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($courses as $course)
                        <x-store.course-card
                            wire:key="course-{{ $course->id }}"
                            :course="$course"
                            :href="route('catalogue.show', $course)"
                            :badge="$badges[$course->getKey()] ?? null"
                            :free="$promoActive"
                            :learners="$course->enrollments_count"
                            :instructor-photo="$course->instructors->first()?->instructorProfile?->photoUrl()" />
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $courses->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
