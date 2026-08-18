{{--
    Course detail (AC-01, phases.md Phase 5).

    The mockup's screen: a full-bleed dark hero carrying the title, meta and
    instructor, with the enrolment card sitting in the hero's right column; then
    "What you'll learn", a collapsible curriculum, and the instructor panel.

    ACCESS BOUNDARY: METADATA ONLY. Lesson TITLES and durations are metadata and
    may be listed; no lesson body, media file, resource or assessment is
    rendered or linked from here, for anyone, enrolled or not (ADR-014 — there
    is no preview exemption in V1). The lock icon on every row is the honest
    signal: this is a table of contents, not a door.

    WHERE THE MOCKUP SHOWS "★ 4.8 (2,340 ratings)" AND "12,480 learners", this
    shows level, duration, lesson count and language. There is no rating table
    and no learner-count figure in this schema — inventing either on a sales
    page would be inventing social proof.
--}}
<div>
    {{-- ══ HERO ══ full-bleed dark, inner content on the 1240px rhythm. --}}
    <div class="bg-teal-900 text-white">
        <div class="mx-auto grid w-full max-w-content items-start gap-8 px-5 py-14 lg:grid-cols-[1fr_380px] lg:gap-16 lg:px-10">

            <div>
                <a href="{{ route('catalogue.index') }}" wire:navigate
                   class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-teal-200 transition-colors hover:text-white">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
                    Back to catalogue
                </a>

                <p class="font-mono text-[11px] font-semibold uppercase tracking-[0.16em] text-teal-300">
                    {{ $course->category?->name ?? 'General' }} · Course
                </p>

                <h1 class="mt-3.5 font-serif text-[42px]/[1.1] font-medium text-white">{{ $course->title }}</h1>

                @if ($course->subtitle)
                    <p class="mt-4 max-w-[58ch] text-base/[1.65] text-white/75">{{ $course->subtitle }}</p>
                @endif

                <div class="mt-6 flex flex-wrap gap-6 text-sm text-white/75">
                    @include('partials.course-rating', ['course' => $course, 'tone' => 'inverse'])

                    {{-- Every enrolment ever granted, not currently-active
                         access — see Show::learnerCount(). Absent entirely
                         until somebody has enrolled: "0 learners" on a sales
                         page is an argument against buying. --}}
                    @if ($learners > 0)
                        <span>{{ number_format($learners) }} {{ Str::plural('learner', $learners) }}</span>
                    @endif

                    <span>{{ $course->level->label() }}</span>

                    @if ($course->total_duration_seconds > 0)
                        <span>{{ intdiv($course->total_duration_seconds, 3600) }}h {{ intdiv($course->total_duration_seconds % 3600, 60) }}m</span>
                    @endif

                    <span>{{ $course->lessons_count }} {{ Str::plural('lesson', $course->lessons_count) }}</span>
                    <span>{{ Str::upper($course->language) }}</span>
                </div>

                @if ($course->instructors->isNotEmpty())
                    <div class="mt-6 flex flex-wrap gap-6">
                        @foreach ($course->instructors as $instructor)
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-teal-500 text-sm font-semibold text-white"
                                     aria-hidden="true">
                                    {{ $instructor->initials() }}
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-white">{{ $instructor->name }}</div>
                                    @if ($instructor->instructorProfile?->headline)
                                        <div class="text-[12.5px] text-white/60">{{ $instructor->instructorProfile->headline }}</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ══ ENROLMENT CARD ══ sits in the hero, as in the mockup. --}}
            <div class="overflow-hidden rounded-card bg-white text-neutral-900 shadow-[0_8px_32px_rgba(5,40,38,0.4)]">
                @include('partials.course-thumb', ['course' => $course])

                <div class="flex flex-col gap-3 px-6 py-[22px]">
                    <div class="font-serif text-3xl font-semibold">{{ $course->price }}</div>

                    <div class="text-[15px] font-medium text-neutral-700">Included in your enrolment</div>

                    <ul class="flex list-none flex-col gap-2 text-[13.5px] text-neutral-600">
                        <li>{{ $course->modules_count }} {{ Str::plural('module', $course->modules_count) }} · {{ $course->lessons_count }} {{ Str::plural('lesson', $course->lessons_count) }}</li>

                        @if ($course->total_duration_seconds > 0)
                            <li>{{ intdiv($course->total_duration_seconds, 3600) }}h {{ intdiv($course->total_duration_seconds % 3600, 60) }}m of video</li>
                        @endif

                        <li>Lifetime access to the material</li>

                        @if ($course->requires_final_test)
                            <li>Final test on completion</li>
                        @endif
                    </ul>

                    {{-- Deliberately disabled. Nothing here may grant access —
                         enrolment comes only from a signature-verified webhook
                         or an audited admin grant (Rules 21–22, ADR-006), and
                         checkout is Phase 12. A button that looked live would
                         be promising something no code behind it can do. --}}
                    <x-button variant="primary" size="lg" class="mt-1.5 w-full" disabled>Purchase course</x-button>

                    <p class="text-center text-[12.5px] text-neutral-500">Enrolment opens soon.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ BODY ══ --}}
    <div class="mx-auto grid w-full max-w-content items-start gap-8 px-5 pb-24 pt-14 lg:grid-cols-[1fr_380px] lg:gap-16 lg:px-10">
        <div class="flex flex-col gap-14">

            @if ($course->description)
                <section>
                    <h2 class="mb-5 font-serif text-[26px] font-medium">About this course</h2>
                    <p class="max-w-measure text-[14.5px]/[1.6] text-neutral-700">{{ $course->description }}</p>
                </section>
            @endif

            @if (! empty($course->outcomes))
                <section>
                    <h2 class="mb-5 font-serif text-[26px] font-medium">What you'll learn</h2>

                    <div class="grid gap-x-8 gap-y-3 sm:grid-cols-2">
                        @foreach ($course->outcomes as $outcome)
                            <div class="flex gap-2.5 text-[14.5px]/[1.55] text-neutral-700">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"
                                     class="mt-0.5 shrink-0 text-teal-600" aria-hidden="true"><path d="M20 6 9 17l-5-5"></path></svg>
                                <span>{{ $outcome }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if (! empty($course->requirements))
                <section>
                    <h2 class="mb-5 font-serif text-[26px] font-medium">Requirements</h2>
                    <ul class="list-inside list-disc space-y-1 text-[14.5px]/[1.55] text-neutral-700">
                        @foreach ($course->requirements as $requirement)
                            <li>{{ $requirement }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section>
                <h2 class="mb-1.5 font-serif text-[26px] font-medium">Course content</h2>

                <div class="mb-5 text-[13.5px] text-neutral-500">
                    {{ $course->modules_count }} {{ Str::plural('section', $course->modules_count) }} ·
                    {{ $course->lessons_count }} {{ Str::plural('lesson', $course->lessons_count) }}
                    @if ($course->total_duration_seconds > 0)
                        · {{ intdiv($course->total_duration_seconds, 3600) }}h {{ intdiv($course->total_duration_seconds % 3600, 60) }}m total
                    @endif
                </div>

                {{-- ONE SECTION OPEN AT A TIME (design handoff, Interactions).
                     The shared index lives on the container, so opening a
                     section closes the previous one; clicking the open section
                     collapses it. The first is open on arrival, because an
                     accordion that starts entirely shut hides the thing the
                     reader came for. --}}
                <div x-data="{ open: 0 }" class="overflow-hidden rounded-md border border-neutral-200 bg-white">
                    @forelse ($curriculum as $module)
                        <div class="border-t border-neutral-200 first:border-t-0"
                             wire:key="module-{{ $module->id }}">

                            @php($i = $loop->index)

                            <button type="button"
                                    x-on:click="open = (open === {{ $i }} ? null : {{ $i }})"
                                    x-bind:aria-expanded="open === {{ $i }} ? 'true' : 'false'"
                                    class="flex w-full cursor-pointer items-center gap-3 bg-white px-5 py-4 text-left transition-colors hover:bg-neutral-50">

                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                     class="shrink-0 text-neutral-600 transition-transform duration-[180ms]"
                                     x-bind:class="open === {{ $i }} ? 'rotate-180' : ''" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>

                                <span class="flex-1 font-sans text-[15px] font-semibold tracking-normal text-neutral-900">
                                    {{ $module->title }}
                                </span>

                                <span class="text-[13px] text-neutral-500">
                                    {{ $module->lessons->count() }} {{ Str::plural('lesson', $module->lessons->count()) }}
                                </span>
                            </button>

                            <div x-show="open === {{ $i }}" x-cloak class="border-t border-neutral-100">
                                @foreach ($module->lessons as $lesson)
                                    <div class="flex items-center gap-3 py-3 pl-[47px] pr-5 text-sm text-neutral-700">
                                        {{-- Locked for everyone on this page, enrolled
                                             or not. The catalogue never links to
                                             content (ADR-014). --}}
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                             class="shrink-0 text-neutral-400" aria-hidden="true">
                                            <rect x="3" y="11" width="18" height="11" rx="2"></rect>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                        </svg>

                                        <span class="flex-1">{{ $lesson->title }}</span>

                                        @if ($lesson->duration_seconds)
                                            <span class="font-mono text-[12.5px] text-neutral-400">
                                                {{ intdiv($lesson->duration_seconds, 60) }}m
                                            </span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-neutral-500">Curriculum coming soon.</p>
                    @endforelse
                </div>
            </section>

            @if ($reviews->isNotEmpty())
                <section>
                    <h2 class="mb-5 font-serif text-[26px] font-medium">What learners say</h2>

                    <div class="flex flex-col gap-4">
                        @foreach ($reviews as $review)
                            <div class="rounded-card border border-neutral-200 bg-white p-6" wire:key="review-{{ $review->id }}">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-teal-600 text-xs font-semibold text-white"
                                         aria-hidden="true">
                                        {{ $review->user?->initials() }}
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-semibold text-neutral-900">{{ $review->user?->name }}</div>
                                        <div class="text-[12.5px] text-neutral-500">{{ $review->created_at?->format('F Y') }}</div>
                                    </div>

                                    {{-- This review's own stars, not the course
                                         mean — so the partial is not reused
                                         here. --}}
                                    <div class="font-semibold text-red-500" aria-hidden="true">
                                        {{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}
                                    </div>
                                    <span class="sr-only">Rated {{ $review->rating }} out of 5</span>
                                </div>

                                <p class="mt-3 text-[14.5px]/[1.6] text-neutral-700">{{ $review->body }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($course->instructors->isNotEmpty())
                <section>
                    <h2 class="mb-5 font-serif text-[26px] font-medium">Your instructor</h2>

                    <div class="flex flex-col gap-5">
                        @foreach ($course->instructors as $instructor)
                            <div class="flex gap-5 rounded-card border border-neutral-200 bg-white p-6">
                                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-teal-600 text-xl font-semibold text-white"
                                     aria-hidden="true">
                                    {{ $instructor->initials() }}
                                </div>

                                <div>
                                    <div class="font-sans text-[17px] font-semibold tracking-normal text-neutral-900">
                                        {{ $instructor->name }}
                                    </div>

                                    @if ($instructor->instructorProfile?->headline)
                                        <div class="mb-2.5 mt-0.5 text-[13.5px] text-neutral-500">
                                            {{ $instructor->instructorProfile->headline }}
                                        </div>
                                    @endif

                                    @if ($instructor->instructorProfile?->bio)
                                        <p class="max-w-[58ch] text-[14.5px]/[1.6] text-neutral-700">
                                            {{ $instructor->instructorProfile->bio }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        {{-- The mockup keeps this column empty below the hero: the enrolment
             card above is the only thing that belongs in it. --}}
        <div></div>
    </div>
</div>
