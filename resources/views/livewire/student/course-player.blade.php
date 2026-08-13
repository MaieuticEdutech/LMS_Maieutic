{{--
    Course player (FR-STU-08, FR-STU-09, NFR-UX-02).

    Two columns on desktop: curriculum sidebar and content area. Below lg the
    sidebar becomes a collapsible panel ABOVE the content rather than a drawer
    — on a phone the curriculum is something you consult between lessons, not
    something you need overlaying the video you are watching.

    The content itself is rendered by whichever partial the registry names for
    this lesson's type, so a new content type needs a handler and a view and
    no change here (ADR-003).
--}}
<div x-data="{ curriculum: false }" class="mx-auto max-w-7xl">

    {{-- ══ HEADER ══ --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <a href="{{ route('student.courses.index') }}"
               class="eyebrow text-neutral-500 underline-offset-4 transition-colors hover:text-teal-700 hover:underline">
                My courses
            </a>
            <h1 class="mt-2 text-2xl text-balance">{{ $course->title }}</h1>
        </div>

        {{-- The course figure, counted from the curriculum on screen so it can
             never disagree with the ticks in the sidebar. --}}
        <div class="w-full shrink-0 sm:w-56">
            <p class="flex items-baseline justify-between font-mono text-xs tracking-[0.04em] text-neutral-500">
                <span>{{ $completedCount }} / {{ $totalCount }} COMPLETE</span>
                <span class="text-neutral-800">{{ $percentage }}%</span>
            </p>
            <x-progress-bar :value="$percentage" class="mt-2" />
        </div>
    </div>

    {{-- ══ COURSE COMPLETE ══
         Shown only once the enrollment itself records completion — which for a
         course with a final test means the test was passed, not merely that
         every lesson was ticked (AC-31). --}}
    @if ($courseCompletedAt)
        <div class="m-motif relative mb-6 overflow-hidden rounded-card bg-teal-900 p-6 text-white">
            <div class="relative">
                <p class="eyebrow text-white/55">Course complete</p>
                <p class="mt-2 font-serif text-xl font-semibold tracking-[-0.015em] text-balance">
                    You finished {{ $course->title }}
                </p>
                <p class="mt-1 text-sm text-white/70">
                    Completed {{ $courseCompletedAt->format('d M Y') }}. Everything stays available to revisit.
                </p>
            </div>
        </div>
    @endif

    {{-- Mobile curriculum toggle. Hidden on desktop, where the sidebar is
         always visible. --}}
    <button type="button" x-on:click="curriculum = ! curriculum"
            class="mb-4 flex w-full items-center justify-between rounded-control border border-neutral-200 bg-white px-4 py-3 text-sm font-medium text-neutral-800 lg:hidden"
            :aria-expanded="curriculum ? 'true' : 'false'">
        <span>Course curriculum</span>
        <span class="font-mono text-xs tracking-[0.04em] text-neutral-500"
              x-text="curriculum ? 'HIDE' : 'SHOW'">SHOW</span>
    </button>

    <div class="grid gap-6 lg:grid-cols-[320px_1fr]">

        {{-- ══ CURRICULUM ══ --}}
        <aside x-show="curriculum || window.innerWidth >= 1024"
               x-on:resize.window="curriculum = window.innerWidth >= 1024 ? curriculum : curriculum"
               class="lg:!block"
               :class="curriculum ? '' : 'hidden lg:block'"
               aria-label="Course curriculum">
            <div class="overflow-hidden rounded-card border border-neutral-200 bg-white">
                @foreach ($curriculum as $module)
                    <div class="border-b border-neutral-100 last:border-b-0">
                        @php $figures = $moduleProgress[$module->id] ?? ['completed' => 0, 'total' => 0, 'percentage' => 0]; @endphp

                        <div class="bg-neutral-50 px-4 py-3">
                            <p class="eyebrow text-neutral-500">Module {{ $loop->iteration }}</p>
                            <p class="mt-1 font-serif text-base font-semibold text-neutral-900">{{ $module->title }}</p>

                            {{-- Module progress is DERIVED, never stored
                                 (ADR-008) — computed here from the lessons and
                                 progress this page already loaded, so the
                                 sidebar costs no extra queries however many
                                 modules a course has. --}}
                            @if ($figures['total'] > 0)
                                <p class="mt-2 font-mono text-[11px] tracking-[0.04em] text-neutral-500">
                                    {{ $figures['completed'] }} / {{ $figures['total'] }} LESSONS
                                </p>
                                <x-progress-bar :value="$figures['percentage']" size="sm" class="mt-1.5" />
                            @endif
                        </div>

                        <ul>
                            @foreach ($module->lessons as $item)
                                @php
                                    $done = ($progress[$item->id] ?? null)?->completed_at !== null;
                                    $current = $item->id === $lesson->id;
                                @endphp
                                <li>
                                    <a href="{{ route('student.courses.play', [$course, $item]) }}"
                                       @if ($current) aria-current="page" @endif
                                       class="flex items-start gap-3 px-4 py-3 text-sm transition-colors {{ $current ? 'bg-teal-50 text-teal-800' : 'text-neutral-700 hover:bg-neutral-50' }}">

                                        {{-- Completion is stated in text for
                                             screen readers, not by colour
                                             alone (WCAG 1.4.1). --}}
                                        <span class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border {{ $done ? 'border-teal-600 bg-teal-600' : 'border-neutral-300' }}">
                                            @if ($done)
                                                <svg class="size-2.5 text-white" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M2 5.5L4 7.5L8 3" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            @endif
                                        </span>

                                        <span class="min-w-0 flex-1">
                                            <span class="block">{{ $item->title }}</span>
                                            @if ($done)
                                                <span class="sr-only">(completed)</span>
                                            @endif
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </aside>

        {{-- ══ CONTENT ══ --}}
        <div class="min-w-0 space-y-6">
            <div>
                <p class="eyebrow text-teal-600">{{ $lesson->type->label() }}</p>
                <h2 class="mt-2 text-2xl text-balance">{{ $lesson->title }}</h2>
            </div>

            {{-- The registry decides this partial. --}}
            @include($playerView, [
                'lesson' => $lesson,
                'media' => $media,
                'progress' => $lessonProgress,
            ])

            {{-- ══ FOOTER: completion + navigation ══ --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-neutral-200 pt-5">
                {{--
                    WHICH CONTROL APPEARS IS THE TYPE'S COMPLETION RULE, asked
                    of the registry rather than matched on the lesson type here
                    (ADR-003, FR-PROG-04).

                    A video completes by being watched and a quiz by being
                    passed, so neither offers a button. Showing one that the
                    server would refuse is worse than showing none — it reads
                    as broken rather than as a rule.
                --}}
                @php $done = $lessonProgress?->completed_at !== null; @endphp

                @if ($done)
                    <div class="flex items-center gap-2">
                        <x-badge variant="success">Completed</x-badge>

                        @if ($strategy->allowsManualCompletion())
                            <button type="button" wire:click="toggleComplete" wire:loading.attr="disabled"
                                    class="text-sm text-neutral-500 underline-offset-4 transition-colors hover:text-neutral-800 hover:underline">
                                Mark as not complete
                            </button>
                        @endif
                    </div>
                @elseif ($strategy->allowsManualCompletion())
                    <x-button wire:click="toggleComplete" variant="primary" wire:loading.attr="disabled">
                        Mark as complete
                    </x-button>
                @elseif ($strategy === \App\Enums\CompletionStrategy::VideoThreshold)
                    <p class="text-sm text-neutral-500">
                        This lesson completes once you have watched {{ $videoThreshold }}% of the video.
                    </p>
                @else
                    <p class="text-sm text-neutral-500">
                        This lesson completes when you pass its assessment.
                    </p>
                @endif

                <div class="flex items-center gap-2">
                    @if ($neighbours['previous'])
                        <x-button :href="route('student.courses.play', [$course, $neighbours['previous']])"
                                  variant="secondary" size="sm">
                            Previous
                        </x-button>
                    @endif

                    @if ($neighbours['next'])
                        <x-button :href="route('student.courses.play', [$course, $neighbours['next']])"
                                  variant="secondary" size="sm">
                            Next lesson
                        </x-button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
