{{--
    Student dashboard (FR-STU-05, FR-STU-07), Spotlight × Canvas.

    ═════════════════════════════════════════════════════════════════════════
    EVERY FIGURE HERE IS MEASURED. Hours and lessons-this-month come from
    lesson_progress, certificates from the certificates table, progress from
    the cached enrolment figures. Nothing is a placeholder a learner could
    mistake for a claim — see StudentDashboardService.

    Three states, in priority order: a returning student (hero with a resume
    link, continue-learning card), someone enrolled but not yet started, and a
    brand-new account. The empty state matters more than it looks — on day one
    EVERY student sees it.
    ═════════════════════════════════════════════════════════════════════════
--}}
@php
    $firstName = auth()->user()->editableNameParts()['first'];
    $enrolled = $enrollments->isNotEmpty();
    $continueCourse = $continue?->course;
@endphp

<div class="mx-auto w-full max-w-content px-5 pb-24 pt-8 lg:px-10">

    {{-- ══ HERO ══ the Canvas arch, made personal. --}}
    <section class="relative overflow-hidden rounded-hero bg-gradient-to-br from-marigold via-white to-teal-50 px-6 py-10 sm:px-10 lg:px-14 lg:py-14">
        <div class="grid items-center gap-10 lg:grid-cols-[1.1fr_0.9fr]">
            <div>
                <p class="eyebrow text-teal-600">{{ now()->format('l, j F') }}</p>

                <h1 class="mt-2.5 font-serif text-[40px]/[1.08] font-normal tracking-[-0.02em] text-neutral-900 md:text-[52px]">
                    Welcome back, <em class="italic text-teal-600">{{ $firstName }}</em>
                </h1>

                <p class="mt-3 max-w-[46ch] text-base/[1.6] text-neutral-600">Continue learning and reach your goals.</p>

                @if ($continueCourse)
                    <div class="mt-7 flex flex-wrap items-center gap-x-5 gap-y-3">
                        <x-button :href="route('student.courses.play', $continueCourse)" size="lg" :pill="true">Resume learning</x-button>

                        <p class="text-sm text-neutral-600">
                            @if ($continue->lastLesson)
                                Next up: <span class="font-semibold text-neutral-900">{{ $continue->lastLesson->title }}</span>
                            @else
                                <span class="font-semibold text-neutral-900">{{ $continueCourse->title }}</span>
                            @endif
                        </p>
                    </div>
                @else
                    <div class="mt-7">
                        <x-button :href="route('catalogue.index')" size="lg" :pill="true">Explore courses</x-button>
                    </div>
                @endif

                @if ($enrolled)
                    <ul class="mt-7 flex flex-wrap gap-2.5 text-[13px] font-semibold text-neutral-700">
                        <li class="rounded-full bg-white/80 px-3.5 py-1.5 ring-1 ring-neutral-200">
                            {{ $stats['lessons_this_month'] }} {{ Str::plural('lesson', $stats['lessons_this_month']) }} this month
                        </li>
                        <li>
                            <a href="{{ route('student.certificates.index') }}"
                               class="block rounded-full bg-white/80 px-3.5 py-1.5 ring-1 ring-neutral-200 hover:ring-neutral-300">
                                {{ $stats['certificates'] }} {{ Str::plural('certificate', $stats['certificates']) }}
                            </a>
                        </li>
                    </ul>
                @endif
            </div>

            <x-store.arch-photo :src="asset('images/storefront/learner.jpg')" class="mx-auto hidden min-h-[320px] w-full max-w-[460px] lg:block">
                <x-store.glass-card class="absolute left-0 top-[14%] w-[200px]">
                    <div class="flex items-center gap-3">
                        <x-store.progress-ring :value="$overall['percentage']" :size="64" />
                        <p class="text-[12.5px] font-semibold leading-tight text-neutral-700">Overall progress</p>
                    </div>
                </x-store.glass-card>

                @if ($continueCourse && $continue->lastLesson)
                    <x-store.glass-card class="absolute bottom-[10%] right-0 w-[210px]" :delay="2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-teal-600">Up next</p>
                        <p class="mt-1 text-[13px] font-semibold leading-snug text-neutral-900">{{ $continue->lastLesson->title }}</p>
                        <p class="mt-1 text-xs text-neutral-500">{{ (int) $continue->progress_percentage }}% of the course done</p>
                    </x-store.glass-card>
                @endif
            </x-store.arch-photo>
        </div>
    </section>

    @if ($enrolled)
        {{-- ══ STATS ══ the tested labels are kept: "Hours learned",
             "Courses in progress", "Lessons this month", "Certificates earned". --}}
        <x-store.stat-strip class="relative z-10 mx-4 -mt-8 md:mx-10" :items="[
            ['value' => $stats['hours'].'h', 'label' => 'Hours learned'],
            ['value' => (string) $stats['in_progress'], 'label' => 'Courses in progress'],
            ['value' => (string) $stats['lessons_this_month'], 'label' => 'Lessons this month'],
            ['value' => (string) $stats['certificates'], 'label' => 'Certificates earned'],
        ]" />
    @endif

    {{-- ══ YOUR WEEK ══ "Your week, planned" is its own component, built
         separately; it sits directly below the hero and the stat strip. --}}
    <div class="mt-8">
        <livewire:student.week-planner />
    </div>

    @if (! $enrolled)
        <div class="mt-10">
            <x-empty-state
                title="You are not enrolled in any courses yet"
                description="Once an administrator grants you access, or you purchase a course, it will appear here with your progress."
            />
        </div>
    @else
        {{-- ══ CONTINUE LEARNING ══ absent, rather than shown empty, for a
             student who has never opened a lesson. --}}
        @if ($continueCourse)
            <div class="mt-14">
                <x-store.section-heading title="Continue learning" :href="route('student.courses.index')" link-label="View all" />

                <div class="flex flex-col overflow-hidden rounded-[20px] border border-neutral-200 bg-white shadow-md transition hover:shadow-lg sm:flex-row">
                    <div class="sm:w-[38%]">
                        <x-store.course-art :course="$continueCourse" class="h-full min-h-[160px]" />
                    </div>

                    <div class="flex flex-1 flex-col gap-2 px-6 py-5">
                        <h3 class="font-sans text-[17px]/[1.3] font-bold text-neutral-900">{{ $continueCourse->title }}</h3>

                        <p class="text-[13.5px] text-neutral-500">
                            @if ($continue->lastLesson)
                                Next: {{ $continue->lastLesson->title }}
                            @else
                                {{ $continueCourse->lessons_count }} {{ Str::plural('lesson', $continueCourse->lessons_count) }} · {{ $continueCourse->level->label() }}
                            @endif
                        </p>

                        <div class="mt-auto flex flex-col gap-2.5 pt-2">
                            <div class="flex items-center gap-2.5">
                                <x-progress-bar :value="(int) $continue->progress_percentage" class="flex-1" />
                                <span class="font-mono text-xs font-semibold text-neutral-700">{{ (int) $continue->progress_percentage }}%</span>
                            </div>

                            <div>
                                <x-button :href="route('student.courses.play', $continueCourse)" size="sm">Continue learning</x-button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ══ YOUR COURSES ══ --}}
        <div class="mt-14">
            <x-store.section-heading title="Your courses" :href="route('catalogue.index')" link-label="Explore all courses" />

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($enrollments->take(6) as $enrollment)
                    @include('livewire.student.partials.course-card', ['enrollment' => $enrollment])
                @endforeach
            </div>
        </div>
    @endif

    {{-- ══ RECOMMENDED FOR YOU ══
         Shown to a brand-new account too: on day one it is the only thing on
         the page worth doing. Absent only when there is genuinely nothing
         published they are not already in. --}}
    @if ($recommended->isNotEmpty())
        <div class="mt-14">
            <x-store.section-heading title="Recommended for you" :href="route('catalogue.index')" link-label="Explore all courses" />

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($recommended as $course)
                    <x-store.course-card
                        wire:key="recommended-{{ $course->id }}"
                        :course="$course"
                        :href="route('catalogue.show', $course)"
                        :free="$free"
                        :instructor-photo="$course->instructors->first()?->instructorProfile?->photoUrl()"
                    />
                @endforeach
            </div>
        </div>
    @endif

    {{-- ══ TOP 10 THIS WEEK ══ real enrolment counts from the last 7 days;
         hidden when nothing has been enrolled in. --}}
    @if ($top->isNotEmpty())
        <div class="mt-14">
            <x-store.section-heading title="Top 10 this week" subtitle="What learners are starting right now." :href="route('catalogue.index')" link-label="Explore all courses" />

            <div class="-mx-5 flex gap-6 overflow-x-auto px-5 pb-4 lg:mx-0 lg:px-0">
                @foreach ($top as $rank => $course)
                    <x-store.rank-card
                        wire:key="top-{{ $course->id }}"
                        class="w-[230px] shrink-0 md:w-[270px]"
                        :course="$course"
                        :rank="$rank"
                        :href="route('catalogue.show', $course)"
                    />
                @endforeach
            </div>
        </div>
    @endif
</div>
