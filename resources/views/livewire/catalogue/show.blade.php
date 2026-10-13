{{--
    Course detail (AC-01, phases.md Phase 5) — Spotlight × Canvas.

    A dark hero with the title, meta and the lead instructor in the Canvas arch;
    then the body on the left with the enrolment card sticking on the right as
    you scroll (and a slim bar pinned to the bottom on a phone).

    ACCESS BOUNDARY: METADATA ONLY. Lesson TITLES and durations are metadata and
    may be listed; no lesson body, media file, resource or assessment is
    rendered or linked from here, for anyone, enrolled or not (ADR-014 — there
    is no preview exemption in V1). The lock icon on every row is the honest
    signal: this is a table of contents, not a door.

    Every figure is real: rating and review count from the course row, the
    learner count from enrolments, instructor numbers from StorefrontService.
    A figure with nothing behind it is not rendered.
--}}
@php
    $lead = $course->instructors->first();
    $leadPhoto = $lead?->instructorProfile?->photoUrl();
    $duration = $course->total_duration_seconds > 0
        ? intdiv($course->total_duration_seconds, 3600).'h '.intdiv($course->total_duration_seconds % 3600, 60).'m'
        : null;
@endphp
<div>
    {{-- ══ HERO ══ --}}
    <div class="overflow-hidden bg-teal-900 text-white">
        <div class="mx-auto grid w-full max-w-content items-center gap-10 px-5 py-14 lg:grid-cols-[1fr_400px] lg:gap-16 lg:px-10">

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

                <h1 class="mt-3.5 font-serif text-[40px]/[1.08] font-normal tracking-[-0.02em] text-white md:text-[56px]">{{ $course->title }}</h1>

                @if ($course->subtitle)
                    <p class="mt-4 max-w-[58ch] text-base/[1.65] text-white/75">{{ $course->subtitle }}</p>
                @endif

                <div class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm text-white/75">
                    @include('partials.course-rating', ['course' => $course, 'tone' => 'inverse'])

                    {{-- Every enrolment ever granted, not currently-active
                         access — see Show::learnerCount(). Absent entirely
                         until somebody has enrolled: "0 learners" on a sales
                         page is an argument against buying. --}}
                    @if ($learners > 0)
                        <span>{{ number_format($learners) }} {{ Str::plural('learner', $learners) }}</span>
                    @endif

                    <span>{{ $course->level->label() }}</span>

                    @if ($duration)
                        <span>{{ $duration }}</span>
                    @endif

                    <span>{{ $course->lessons_count }} {{ Str::plural('lesson', $course->lessons_count) }}</span>
                    <span>{{ Str::upper($course->language) }}</span>
                </div>

                @if ($course->instructors->isNotEmpty())
                    <div class="mt-7 flex flex-wrap gap-6">
                        @foreach ($course->instructors as $instructor)
                            <div class="flex items-center gap-3">
                                <x-store.avatar :name="$instructor->name" :photo="$instructor->instructorProfile?->photoUrl()" :size="44" />
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

            {{-- The Canvas arch: the lead instructor's photo when there is one,
                 otherwise the course's own cover. Never a stock face. --}}
            <div class="hidden lg:block">
                @if ($leadPhoto)
                    <x-store.arch-photo :src="$leadPhoto" position="center top" class="h-[440px] w-full">
                        <x-store.glass-card class="absolute bottom-[8%] left-0 max-w-[240px]">
                            <div class="text-[13px] font-bold text-neutral-900">{{ $lead->name }}</div>
                            @if ($lead->instructorProfile?->headline)
                                <div class="mt-0.5 text-[12.5px] text-neutral-600">{{ $lead->instructorProfile->headline }}</div>
                            @endif
                        </x-store.glass-card>
                    </x-store.arch-photo>
                @else
                    <x-store.course-art :course="$course" ratio="4/5" class="rounded-arch shadow-float" />
                @endif
            </div>
        </div>
    </div>

    {{-- ══ BODY ══ --}}
    <div class="mx-auto grid w-full max-w-content items-start gap-8 px-5 pb-28 pt-14 lg:grid-cols-[1fr_380px] lg:gap-16 lg:px-10">
        <div class="order-2 flex flex-col gap-14 lg:order-1">

            @if ($course->description)
                <section>
                    <h2 class="mb-5 font-serif text-[30px] font-normal tracking-[-0.02em]">About this course</h2>
                    <p class="max-w-measure text-[14.5px]/[1.6] text-neutral-700">{{ $course->description }}</p>
                </section>
            @endif

            @if (! empty($course->outcomes))
                <section>
                    <h2 class="mb-5 font-serif text-[30px] font-normal tracking-[-0.02em]">What you'll learn</h2>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($course->outcomes as $outcome)
                            <div class="flex gap-2.5 rounded-[14px] border border-neutral-200 bg-white p-4 text-[14.5px]/[1.55] text-neutral-700">
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
                    <h2 class="mb-5 font-serif text-[30px] font-normal tracking-[-0.02em]">Requirements</h2>
                    <ul class="list-inside list-disc space-y-1 text-[14.5px]/[1.55] text-neutral-700">
                        @foreach ($course->requirements as $requirement)
                            <li>{{ $requirement }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section>
                <h2 class="mb-1.5 font-serif text-[30px] font-normal tracking-[-0.02em]">Course content</h2>

                <div class="mb-5 text-[13.5px] text-neutral-500">
                    {{ $course->modules_count }} {{ Str::plural('section', $course->modules_count) }} ·
                    {{ $course->lessons_count }} {{ Str::plural('lesson', $course->lessons_count) }}
                    @if ($duration)
                        · {{ $duration }} total
                    @endif
                </div>

                {{-- ONE CHAPTER OPEN AT A TIME. The shared index lives on the
                     container, so opening a chapter closes the previous one;
                     clicking the open one collapses it. The first is open on
                     arrival, because an accordion that starts entirely shut
                     hides the thing the reader came for. --}}
                <div x-data="{ open: 0 }" class="overflow-hidden rounded-[20px] border border-neutral-200 bg-white">
                    @forelse ($curriculum as $module)
                        <div class="border-t border-neutral-200 first:border-t-0"
                             wire:key="module-{{ $module->id }}">

                            @php($i = $loop->index)

                            <button type="button"
                                    x-on:click="open = (open === {{ $i }} ? null : {{ $i }})"
                                    x-bind:aria-expanded="open === {{ $i }} ? 'true' : 'false'"
                                    class="flex w-full cursor-pointer items-center gap-4 bg-white px-5 py-4 text-left transition-colors hover:bg-neutral-50">

                                <span class="numeral-outline w-10 shrink-0 text-center font-serif text-[40px] leading-none" aria-hidden="true">{{ $loop->iteration }}</span>

                                <span class="flex-1 font-sans text-[15px] font-semibold tracking-normal text-neutral-900">
                                    {{ $module->title }}
                                </span>

                                <span class="text-[13px] text-neutral-500">
                                    {{ $module->lessons->count() }} {{ Str::plural('lesson', $module->lessons->count()) }}
                                </span>

                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                     class="shrink-0 text-neutral-600 transition-transform duration-[180ms]"
                                     x-bind:class="open === {{ $i }} ? 'rotate-180' : ''" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                            </button>

                            <div x-show="open === {{ $i }}" x-cloak class="border-t border-neutral-100">
                                @foreach ($module->lessons as $lesson)
                                    <div class="flex items-center gap-3 py-3 pl-[76px] pr-5 text-sm text-neutral-700">
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

            @if ($course->instructors->isNotEmpty())
                <section>
                    <h2 class="mb-5 font-serif text-[30px] font-normal tracking-[-0.02em]">Your instructor</h2>

                    <div class="flex flex-col gap-5">
                        @foreach ($course->instructors as $instructor)
                            @php($stats = $instructorStats[$instructor->getKey()] ?? null)
                            <div class="flex gap-5 rounded-[20px] border border-neutral-200 bg-white p-6">
                                <x-store.avatar :name="$instructor->name" :photo="$instructor->instructorProfile?->photoUrl()" :size="72" />

                                <div>
                                    <div class="font-sans text-[17px] font-semibold tracking-normal text-neutral-900">
                                        {{ $instructor->name }}
                                    </div>

                                    @if ($instructor->instructorProfile?->headline)
                                        <div class="mb-2.5 mt-0.5 text-[13.5px] text-neutral-500">
                                            {{ $instructor->instructorProfile->headline }}
                                        </div>
                                    @endif

                                    @if ($stats && $stats['courses'] > 0)
                                        <p class="mb-2.5 flex flex-wrap gap-x-4 gap-y-1 text-[12.5px] font-semibold text-neutral-600">
                                            <span>{{ $stats['courses'] }} {{ Str::plural('course', $stats['courses']) }}</span>
                                            @if ($stats['learners'] > 0)
                                                <span>{{ number_format($stats['learners']) }} {{ Str::plural('learner', $stats['learners']) }}</span>
                                            @endif
                                            @if ($stats['rating'] !== null)
                                                <span>{{ number_format($stats['rating'], 1) }} instructor rating</span>
                                            @endif
                                        </p>
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

            @if ($course->hasRatings())
                <section>
                    <h2 class="mb-5 font-serif text-[30px] font-normal tracking-[-0.02em]">Learner ratings</h2>

                    <div class="flex flex-wrap items-center gap-8 rounded-[20px] border border-neutral-200 bg-white p-6">
                        <div class="text-center">
                            <div class="font-serif text-[56px] leading-none text-neutral-900">{{ number_format((float) $course->averageRating(), 1) }}</div>
                            <x-store.rating :course="$course" :stars="true" class="mt-2" />
                        </div>

                        <div class="min-w-[220px] flex-1 space-y-2">
                            @foreach ($breakdown as $star => $count)
                                @php($share = $course->rating_count > 0 ? round($count / $course->rating_count * 100) : 0)
                                <div class="flex items-center gap-3 text-[12.5px] text-neutral-600">
                                    <span class="w-10 shrink-0">{{ $star }} {{ Str::plural('star', $star) }}</span>
                                    <span class="h-2 flex-1 overflow-hidden rounded-full bg-neutral-100" aria-hidden="true">
                                        <span class="block h-full rounded-full bg-red-500" style="width: {{ $share }}%"></span>
                                    </span>
                                    <span class="w-9 shrink-0 text-right">{{ $share }}%</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            @if ($reviews->isNotEmpty())
                <section>
                    <h2 class="mb-5 font-serif text-[30px] font-normal tracking-[-0.02em]">What learners say</h2>

                    <div class="flex flex-col gap-4">
                        @foreach ($reviews as $review)
                            <div class="rounded-[20px] border border-neutral-200 bg-white p-6" wire:key="review-{{ $review->id }}">
                                <div class="flex items-center gap-3">
                                    <x-store.avatar :name="$review->user?->name ?? ''" :size="36" />

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
        </div>

        {{-- ══ ENROLMENT CARD ══ sticks beside the content on a wide screen
             and leads the body on a phone. --}}
        <div id="enrol" class="order-1 scroll-mt-24 lg:sticky lg:top-24 lg:order-2">
            <div class="overflow-hidden rounded-[20px] border border-neutral-200 bg-white text-neutral-900 shadow-lg">
                <x-store.course-art :course="$course" :label="false" />

                <div class="flex flex-col gap-3 px-6 py-[22px]">
                    @if ($promoActive)
                        {{-- Free-launch promotion (planning.md §16.4): the real price,
                             struck through, never changed or hidden. --}}
                        <div>
                            <div class="font-serif text-4xl text-teal-700">Free</div>
                            <div class="mt-0.5 text-sm text-neutral-500">
                                <s>{{ $course->price }}</s>
                                <span class="ml-1.5 font-medium text-teal-700">Free for a limited time</span>
                            </div>
                            @if ($promoEndsAt)
                                <div class="mt-1.5 text-[12.5px] text-neutral-500">
                                    <x-store.countdown :ends="$promoEndsAt" />
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="font-serif text-4xl">{{ $course->price }}</div>
                    @endif

                    <div class="text-[15px] font-medium text-neutral-700">Included in your enrolment</div>

                    <ul class="flex list-none flex-col gap-2 text-[13.5px] text-neutral-600">
                        <li>{{ $course->modules_count }} {{ Str::plural('module', $course->modules_count) }} · {{ $course->lessons_count }} {{ Str::plural('lesson', $course->lessons_count) }}</li>

                        @if ($duration)
                            <li>{{ $duration }} of video</li>
                        @endif

                        <li>Lifetime access to the material</li>

                        @if ($course->requires_final_test)
                            <li>Final test on completion</li>
                        @endif
                    </ul>

                    {{-- Leads to checkout, never grants anything itself —
                         enrolment still comes only from a signature-verified
                         webhook or an audited admin grant (Rules 21–22,
                         ADR-006). Phase 12's InitiateCheckout is what this
                         button reaches. --}}
                    @if ($promoActive)
                        {{-- The purchase button below is hidden, not removed: it
                             returns when the promotion ends. --}}
                        @if ($viewerHasAccess)
                            <x-button :href="route('student.courses.play', ['course' => $course])" variant="primary" size="lg" pill class="mt-1.5 w-full">
                                Go to course
                            </x-button>
                        @elseif (auth()->guest())
                            <x-button :href="route('register')" variant="primary" size="lg" pill class="mt-1.5 w-full">
                                Join free to enrol
                            </x-button>
                            <p class="text-center text-[12.5px] text-neutral-500">
                                Already have an account? <a href="{{ route('login') }}" class="font-medium text-teal-700 hover:underline">Sign in</a>
                            </p>
                        @elseif (auth()->user()->isStudent())
                            <form method="POST" action="{{ route('catalogue.enrol-free', ['course' => $course]) }}">
                                @csrf
                                <x-button type="submit" variant="primary" size="lg" pill class="mt-1.5 w-full">Enrol free</x-button>
                            </form>
                            <p class="text-center text-[12.5px] text-neutral-500">Course and certificate included.</p>
                        @endif
                    @else
                        <x-button :href="route('checkout.start', ['course' => $course])" variant="primary" size="lg" pill class="mt-1.5 w-full" wire:navigate>
                            Purchase course
                        </x-button>

                        <p class="text-center text-[12.5px] text-neutral-500">Secure checkout via Razorpay.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- A phone keeps the price in reach while reading: a slim bar that jumps
         to the enrolment card. Purely navigation; the card does the work. --}}
    <div class="fixed inset-x-0 bottom-0 z-40 flex items-center justify-between gap-4 border-t border-neutral-200 bg-white/95 px-5 py-3 backdrop-blur lg:hidden">
        <div class="min-w-0 truncate text-sm font-semibold text-neutral-900">
            {{ $promoActive ? 'Free for now' : $course->price }}
        </div>
        <a href="#enrol" class="rounded-full bg-teal-600 px-5 py-2 text-sm font-semibold text-white">Enrolment details</a>
    </div>
</div>
