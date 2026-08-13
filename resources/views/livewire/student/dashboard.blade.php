{{--
    Student dashboard (FR-STU-05, FR-STU-07).

    Three states, in priority order: a continue-learning card for a returning
    student, a course grid for someone enrolled but not yet started, and an
    empty state for a brand-new account.

    The empty state matters more than it looks — on day one EVERY student sees
    it, and it is the first impression the product makes.
--}}
<div class="space-y-10">

    <div>
        <p class="eyebrow text-teal-600">Your learning</p>
        <h1 class="mt-2 text-[2rem]">Welcome back, {{ str(auth()->user()->name)->before(' ') }}</h1>
    </div>

    @if ($enrollments->isEmpty())
        <x-empty-state
            title="You are not enrolled in any courses yet"
            description="Once an administrator grants you access, or you purchase a course, it will appear here with your progress."
        />
    @else
        {{-- ══ CONTINUE LEARNING ══ the single most useful control on the
             page for anyone who has started something. --}}
        @if ($continue?->course)
            <section aria-labelledby="continue-heading"
                     class="m-motif relative overflow-hidden rounded-card bg-teal-900 p-8 text-white">
                <div class="relative max-w-[52ch]">
                    <p class="eyebrow text-white/55">Continue learning</p>

                    <h2 id="continue-heading" class="mt-3 font-serif text-3xl font-semibold tracking-[-0.015em] text-balance">
                        {{ $continue->course->title }}
                    </h2>

                    @if ($continue->progress_percentage > 0)
                        <div class="mt-4 max-w-sm">
                            <p class="font-mono text-xs tracking-[0.04em] text-white/60">
                                {{ $continue->progress_percentage }}% COMPLETE
                            </p>
                            <x-progress-bar :value="$continue->progress_percentage" tone="inverse" class="mt-2" />
                        </div>
                    @endif

                    <div class="mt-6">
                        <a href="{{ route('student.courses.play', $continue->course) }}"
                           class="inline-flex h-10 items-center rounded-control bg-white px-4 text-sm font-medium text-teal-900 transition-colors hover:bg-neutral-100">
                            Resume course
                        </a>
                    </div>
                </div>
            </section>
        @endif

        {{-- ══ STATS ══ --}}
        <section aria-label="Your progress" class="space-y-4">

            {{-- Overall progress (FR-PROG-07). A MEAN OF COURSE PERCENTAGES,
                 not of lessons: someone halfway through two courses is 50%
                 whether one has ten lessons and the other a hundred, which is
                 how a person actually thinks about their own progress. --}}
            <div class="rounded-card border border-neutral-200 bg-white p-5">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="eyebrow text-neutral-500">Overall progress</p>
                    <p class="font-serif text-2xl font-semibold tracking-[-0.015em] text-neutral-900">
                        {{ $overall['percentage'] }}%
                    </p>
                </div>

                <x-progress-bar :value="$overall['percentage']" class="mt-3" />

                {{-- Built as one expression rather than inline @if/@endif:
                     Blade will not compile a directive glued to a word
                     character, so `finished@endif` silently leaves the @if
                     open and the whole view fails to parse. --}}
                <p class="mt-3 text-sm text-neutral-500">
                    Across {{ $overall['courses'] }} {{ Str::plural('course', $overall['courses']) }}{{ $overall['completed'] > 0 ? ', '.$overall['completed'].' finished' : '' }}.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat-tile label="Enrolled" :value="$stats['enrolled']" />
                <x-stat-tile label="In progress" :value="$stats['in_progress']" />
                <x-stat-tile label="Completed" :value="$stats['completed']" />
            </div>
        </section>

        {{-- ══ COURSES ══ --}}
        <section aria-labelledby="courses-heading" class="space-y-5">
            <div class="flex items-end justify-between gap-4">
                <h2 id="courses-heading" class="text-xl">Your courses</h2>
                <a href="{{ route('student.courses.index') }}"
                   class="text-sm font-medium text-teal-600 underline-offset-[0.18em] transition-colors hover:text-teal-700 hover:underline">
                    View all
                </a>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($enrollments->take(6) as $enrollment)
                    @include('livewire.student.partials.course-card', ['enrollment' => $enrollment])
                @endforeach
            </div>
        </section>
    @endif
</div>
