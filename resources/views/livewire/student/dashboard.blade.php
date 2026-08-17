{{--
    Student dashboard (FR-STU-05, FR-STU-07).

    Follows the mockup's dashboard: dated eyebrow, serif welcome, a row of four
    stat tiles, "Continue learning" as wide two-up cards, then a course grid.

    ═════════════════════════════════════════════════════════════════════════
    THE MOCKUP'S FOUR STATS ARE NOT ALL THINGS THIS SYSTEM KNOWS.

    It shows "42h hours learned", "3 courses in progress", "17 lessons this
    month" and "2 certificates earned". Two of those are real: in-progress and
    completed counts come from StudentDashboardService.

    The other two are not. Nothing records watch time as hours, and there is no
    certificate anywhere in this codebase — no model, no migration, no issuing
    rule. Printing "42h" and "2 certificates" would be inventing figures on a
    screen a learner will believe, so the two slots hold what the system can
    actually stand behind: total enrolled, and overall progress across them.

    The layout is the mockup's. The numbers are ours.
    ═════════════════════════════════════════════════════════════════════════

    Three states, in priority order: continue-learning cards for a returning
    student, a course grid for someone enrolled but not yet started, and an
    empty state for a brand-new account. The empty state matters more than it
    looks — on day one EVERY student sees it, and it is the first impression
    the product makes.
--}}
<div class="mx-auto w-full max-w-content px-5 pb-24 pt-12 lg:px-10">

    <p class="eyebrow text-teal-600">{{ now()->format('l, j F') }}</p>

    <h1 class="mt-2.5 font-serif text-[40px]/[1.1] font-medium">
        Welcome back, {{ auth()->user()->editableNameParts()['first'] }}
    </h1>

    <p class="mt-2 text-base/[1.6] text-neutral-500">Continue learning and reach your goals.</p>

    @if ($enrollments->isEmpty())
        <div class="mt-10">
            <x-empty-state
                title="You are not enrolled in any courses yet"
                description="Once an administrator grants you access, or you purchase a course, it will appear here with your progress."
            />
        </div>
    @else
        {{-- ══ STATS ══ four tiles, serif figure over a small label.

             THE DESIGN'S FOUR LABELS, BUT ONLY TWO REAL FIGURES.

             "Courses in progress" and the progress percentage come from
             StudentDashboardService and ProgressCalculator. "Hours learned",
             "Lessons this month" and "Certificates earned" have no source in
             this system — no watch-time aggregate, no certificates domain —
             so they come from DemoMetrics and are marked as placeholders on
             the tile itself. Production falls back to the honest figure.
             See App\Support\DemoMetrics. --}}
        @php($demo = \App\Support\DemoMetrics::enabled() ? \App\Support\DemoMetrics::dashboardStats() : null)

        <div class="mb-14 mt-10 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-card border border-neutral-200 bg-white px-[22px] py-5">
                <div class="flex items-start justify-between gap-2">
                    <div class="font-serif text-[32px]/none font-medium text-neutral-900">
                        {{ $demo['hours_learned'] ?? $stats['enrolled'] }}
                    </div>
                    <x-sample-data-badge />
                </div>
                <div class="mt-2 text-[13px] font-medium text-neutral-500">
                    {{ $demo ? 'Hours learned' : 'Courses enrolled' }}
                </div>
            </div>

            {{-- Real, in every environment. --}}
            <div class="rounded-card border border-neutral-200 bg-white px-[22px] py-5">
                <div class="font-serif text-[32px]/none font-medium text-neutral-900">{{ $stats['in_progress'] }}</div>
                <div class="mt-2 text-[13px] font-medium text-neutral-500">Courses in progress</div>
            </div>

            <div class="rounded-card border border-neutral-200 bg-white px-[22px] py-5">
                <div class="flex items-start justify-between gap-2">
                    <div class="font-serif text-[32px]/none font-medium text-neutral-900">
                        {{ $demo['lessons_this_month'] ?? $stats['completed'] }}
                    </div>
                    <x-sample-data-badge />
                </div>
                <div class="mt-2 text-[13px] font-medium text-neutral-500">
                    {{ $demo ? 'Lessons this month' : 'Courses completed' }}
                </div>
            </div>

            {{-- Overall progress (FR-PROG-07) when real. A MEAN OF COURSE
                 PERCENTAGES, not of lessons: someone halfway through two
                 courses is 50% whether one has ten lessons and the other a
                 hundred, which is how a person actually thinks about their
                 own progress. --}}
            <div class="rounded-card border border-neutral-200 bg-white px-[22px] py-5">
                <div class="flex items-start justify-between gap-2">
                    <div class="font-serif text-[32px]/none font-medium text-neutral-900">
                        {{ $demo['certificates'] ?? $overall['percentage'].'%' }}
                    </div>
                    <x-sample-data-badge />
                </div>
                <div class="mt-2 text-[13px] font-medium text-neutral-500">
                    {{ $demo ? 'Certificates earned' : 'Overall progress' }}
                </div>
            </div>
        </div>

        {{-- ══ CONTINUE LEARNING ══ the single most useful control on the page
             for anyone who has started something. Absent, rather than shown
             empty, for a student who has never opened a lesson. --}}
        @if ($continue?->course)
            @php($continueCourse = $continue->course)

            <div class="mb-5 flex items-baseline justify-between">
                <h2 class="font-serif text-[26px] font-medium tracking-[-0.01em]">Continue learning</h2>

                <a href="{{ route('student.courses.index') }}" class="text-sm font-medium text-teal-600 hover:text-teal-700">
                    View all
                </a>
            </div>

            <div class="mb-14 grid gap-5 lg:grid-cols-2">
                <div class="flex overflow-hidden rounded-card border border-neutral-200 bg-white transition-all duration-[180ms] ease-standard hover:border-neutral-300 hover:shadow-[0_2px_8px_rgba(26,24,21,0.06)]">
                    @include('partials.course-thumb', ['course' => $continueCourse, 'variant' => 'side'])

                    <div class="flex flex-1 flex-col gap-1.5 px-6 py-5">
                        <h3 class="font-sans text-[17px]/[1.3] font-semibold tracking-normal text-neutral-900">
                            {{ $continueCourse->title }}
                        </h3>

                        <p class="text-[13.5px] text-neutral-500">
                            {{ $continueCourse->lessons_count }} {{ Str::plural('lesson', $continueCourse->lessons_count) }} · {{ $continueCourse->level->label() }}
                        </p>

                        <div class="mt-auto flex flex-col gap-[10px]">
                            <div class="flex items-center gap-[10px]">
                                <x-progress-bar :value="(int) $continue->progress_percentage" class="flex-1" />
                                <span class="font-mono text-xs font-semibold text-neutral-700">
                                    {{ (int) $continue->progress_percentage }}%
                                </span>
                            </div>

                            <a href="{{ route('student.courses.play', $continueCourse) }}"
                               class="self-start rounded-sm bg-teal-600 px-[18px] py-[9px] text-[13.5px] font-semibold text-white transition-colors hover:bg-teal-700">
                                Continue learning
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ══ YOUR COURSES ══ --}}
        <div class="mb-5 flex items-baseline justify-between">
            <h2 class="font-serif text-[26px] font-medium tracking-[-0.01em]">Your courses</h2>

            <a href="{{ route('catalogue.index') }}" class="text-sm font-medium text-teal-600 hover:text-teal-700">
                Explore all courses
            </a>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($enrollments->take(6) as $enrollment)
                @include('livewire.student.partials.course-card', ['enrollment' => $enrollment])
            @endforeach
        </div>
    @endif

    {{-- ══ RECOMMENDED FOR YOU ══ (design §1, three-up).

         Published courses this student is not enrolled in. Shown to everyone,
         including the brand-new account above, because a dashboard with
         nothing to do on it is the worst first impression the product can
         make — and this is the one section that always has something. --}}
    @if ($recommended->isNotEmpty())
        <div class="mb-5 mt-14 flex items-baseline justify-between">
            <h2 class="font-serif text-[26px] font-medium tracking-[-0.01em]">Recommended for you</h2>

            <a href="{{ route('catalogue.index') }}" class="text-sm font-medium text-teal-600 hover:text-teal-700">
                Explore all courses
            </a>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($recommended as $course)
                <a href="{{ route('catalogue.show', $course) }}"
                   class="group flex flex-col overflow-hidden rounded-card border border-neutral-200 bg-white transition-all duration-[180ms] ease-standard hover:-translate-y-px hover:border-neutral-300 hover:shadow-[0_2px_8px_rgba(26,24,21,0.06)]">
                    @include('partials.course-thumb', ['course' => $course])

                    <div class="flex flex-1 flex-col gap-1.5 px-5 pb-5 pt-[18px]">
                        <h3 class="font-sans text-base/[1.35] font-semibold tracking-normal text-neutral-900">
                            {{ $course->title }}
                        </h3>

                        <p class="text-[13px] text-neutral-500">
                            {{ $course->instructors->first()?->name ?? 'Maieutic' }}
                        </p>

                        {{-- The design's meta row. The rating is a placeholder
                             — there is no rating or review domain — so it
                             carries the marker beside it. Level and duration
                             are real columns on the row. --}}
                        <div class="mt-auto flex flex-wrap items-center gap-x-2 gap-y-1 pt-2 text-[13px] text-neutral-500">
                            @if (\App\Support\DemoMetrics::enabled())
                                <span class="font-semibold text-[#a31009]">★ {{ \App\Support\DemoMetrics::rating($course->id) }}</span>
                                <span aria-hidden="true">·</span>
                            @endif
                            <span>{{ $course->level->label() }}</span>
                            <span aria-hidden="true">·</span>
                            <span>{{ $course->lessons_count }} {{ Str::plural('lesson', $course->lessons_count) }}</span>
                            <x-sample-data-badge class="ml-auto" />
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
