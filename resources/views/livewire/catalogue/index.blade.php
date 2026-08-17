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

    <h1 class="mt-2.5 font-serif text-[40px]/[1.1] font-medium">Courses</h1>

    <p class="mb-10 mt-2 max-w-[60ch] text-base/[1.6] text-neutral-500">
        Explore courses designed to help you build real-world skills.
    </p>

    <div class="grid items-start gap-10 lg:grid-cols-[240px_1fr]">

        {{-- ══ FILTER RAIL ══ sticks below the 64px header. --}}
        <aside class="flex flex-col gap-7 lg:sticky lg:top-[88px]" aria-label="Filter courses">

            <div>
                <h2 class="mb-3 font-mono text-[11px] font-semibold tracking-[0.14em] text-neutral-700">CATEGORY</h2>

                <div class="flex flex-col gap-[9px]">
                    {{-- Radios, not checkboxes: the query filters on one
                         category. Checkboxes would promise multi-select and
                         then quietly keep only the last one clicked. --}}
                    <label class="flex cursor-pointer items-center gap-[9px] text-sm text-neutral-700">
                        <input type="radio" wire:model.live="category" value="" class="h-[15px] w-[15px] accent-teal-600">
                        All categories
                    </label>

                    @foreach ($categories as $cat)
                        <label class="flex cursor-pointer items-center gap-[9px] text-sm text-neutral-700" wire:key="cat-{{ $cat->id }}">
                            <input type="radio" wire:model.live="category" value="{{ $cat->slug }}" class="h-[15px] w-[15px] accent-teal-600">
                            {{ $cat->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <h2 class="mb-3 font-mono text-[11px] font-semibold tracking-[0.14em] text-neutral-700">LEVEL</h2>

                <div class="flex flex-col gap-[9px]">
                    <label class="flex cursor-pointer items-center gap-[9px] text-sm text-neutral-700">
                        <input type="radio" wire:model.live="level" value="" class="h-[15px] w-[15px] accent-teal-600">
                        Any level
                    </label>

                    @foreach (\App\Enums\CourseLevel::cases() as $case)
                        <label class="flex cursor-pointer items-center gap-[9px] text-sm text-neutral-700" wire:key="level-{{ $case->value }}">
                            <input type="radio" wire:model.live="level" value="{{ $case->value }}" class="h-[15px] w-[15px] accent-teal-600">
                            {{ $case->label() }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <h2 class="mb-3 font-mono text-[11px] font-semibold tracking-[0.14em] text-neutral-700">DURATION</h2>

                <div class="flex flex-col gap-[9px]">
                    <label class="flex cursor-pointer items-center gap-[9px] text-sm text-neutral-700">
                        <input type="radio" wire:model.live="duration" value="" class="h-[15px] w-[15px] accent-teal-600">
                        Any length
                    </label>

                    {{-- Labels come from the same array the query reads, so the
                         band a student picks and the band they get cannot
                         drift. --}}
                    @foreach ($this->durationBands() as $key => $band)
                        <label class="flex cursor-pointer items-center gap-[9px] text-sm text-neutral-700" wire:key="duration-{{ $key }}">
                            <input type="radio" wire:model.live="duration" value="{{ $key }}" class="h-[15px] w-[15px] accent-teal-600">
                            {{ $band['label'] }}
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- The handoff's fourth group, RATING, is absent rather than drawn
                 inert: there is no rating table in this schema, so the facet
                 could only ever be a control that does nothing. --}}
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
                        class="h-9 rounded-sm border border-neutral-200 bg-white px-3 text-[13.5px] font-medium text-neutral-700">
                    <option value="newest">Newest</option>
                    <option value="price-asc">Price: low to high</option>
                    <option value="price-desc">Price: high to low</option>
                </select>
            </div>

            @if ($courses->isEmpty())
                <x-empty-state title="No courses match" description="Try a different search, category or level." />
            @else
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($courses as $course)
                        <a href="{{ route('catalogue.show', $course) }}"
                           wire:navigate
                           wire:key="course-{{ $course->id }}"
                           class="group flex flex-col overflow-hidden rounded-card border border-neutral-200 bg-white transition-all duration-[180ms] ease-standard hover:-translate-y-px hover:border-neutral-300 hover:shadow-[0_2px_8px_rgba(26,24,21,0.06)]">

                            @include('partials.course-thumb', ['course' => $course])

                            <div class="flex flex-1 flex-col gap-1.5 px-5 pb-5 pt-[18px]">
                                <h3 class="font-sans text-base/[1.35] font-semibold tracking-normal text-neutral-900 group-hover:text-teal-700">
                                    {{ $course->title }}
                                </h3>

                                @if ($course->subtitle)
                                    <p class="line-clamp-2 text-[13px] text-neutral-500">{{ $course->subtitle }}</p>
                                @endif

                                <div class="mt-auto flex items-center gap-2 pt-2 text-[13px] text-neutral-600">
                                    <span>{{ $course->level->label() }}</span>
                                    <span class="text-neutral-300">·</span>
                                    <span>{{ $course->lessons_count }} {{ Str::plural('lesson', $course->lessons_count) }}</span>
                                    <span class="ml-auto font-semibold text-neutral-900">{{ $course->price }}</span>
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
</div>
