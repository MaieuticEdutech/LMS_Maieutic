{{--
    Course player (FR-STU-08, FR-STU-09, NFR-UX-02).

    The mockup's player: content on the left, and a full-height curriculum rail
    on the right that sticks below the 64px header and scrolls independently.
    Full-bleed rather than centred in a 1240px column — the mockup gives this
    one screen the whole viewport, because a video and its contents list are
    what the page is for.

    Below lg the rail becomes a collapsible panel ABOVE the content rather than
    a drawer — on a phone the curriculum is something you consult between
    lessons, not something you need overlaying the video you are watching.

    The content itself is rendered by whichever partial the registry names for
    this lesson's type, so a new content type needs a handler and a view and no
    change here (ADR-003).
--}}
<div x-data="{ curriculum: false }" class="grid items-start lg:grid-cols-[1fr_360px]">

    {{-- ══ CONTENT ══ --}}
    <div class="min-w-0 px-5 pb-24 pt-8 lg:px-10">

        {{-- Breadcrumb, as in the mockup: where this lesson sits, not a page
             title repeated from the rail. --}}
        <div class="mb-5 flex flex-wrap items-center gap-2.5 text-[13px] text-neutral-500">
            <a href="{{ route('student.courses.index') }}" class="text-teal-600 hover:text-teal-700">My Learning</a>
            <span>/</span>
            <span>{{ $course->title }}</span>
        </div>

        {{-- ══ COURSE COMPLETE ══
             Shown only once the enrollment itself records completion — which
             for a course with a final test means the test was passed, not
             merely that every lesson was ticked (AC-31). --}}
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

        {{-- Mobile curriculum toggle. Hidden on desktop, where the rail is
             always visible. --}}
        <button type="button" x-on:click="curriculum = ! curriculum"
                class="mb-4 flex w-full items-center justify-between rounded-sm border border-neutral-200 bg-white px-4 py-3 text-sm font-medium text-neutral-800 lg:hidden"
                x-bind:aria-expanded="curriculum ? 'true' : 'false'">
            <span>Course curriculum</span>
            <span class="font-mono text-xs tracking-[0.04em] text-neutral-500"
                  x-text="curriculum ? 'HIDE' : 'SHOW'">SHOW</span>
        </button>

        {{-- The registry decides this partial. It carries the video, text or
             quiz body — everything above and below it is chrome. --}}
        @include($playerView, [
            'lesson' => $lesson,
            'media' => $media,
            'progress' => $lessonProgress,
        ])

        {{-- ══ LESSON HEADING ══ sits BELOW the media, as in the mockup: the
             thing you came to watch leads, and its title annotates it. --}}
        <div class="mt-7 flex flex-wrap items-start justify-between gap-6">
            <div class="min-w-0">
                <p class="eyebrow text-teal-600">{{ $lesson->type->label() }}</p>
                <h1 class="mt-2 font-serif text-[30px]/[1.15] font-medium tracking-[-0.01em] text-balance">
                    {{ $lesson->title }}
                </h1>
            </div>

            {{--
                WHICH CONTROL APPEARS IS THE TYPE'S COMPLETION RULE, asked of the
                registry rather than matched on the lesson type here (ADR-003,
                FR-PROG-04).

                A video completes by being watched and a quiz by being passed, so
                neither offers a button. Showing one the server would refuse is
                worse than showing none — it reads as broken rather than as a
                rule.
            --}}
            @php $done = $lessonProgress?->completed_at !== null; @endphp

            <div class="flex shrink-0 items-center gap-2">
                @if ($done)
                    <x-badge variant="success">Completed</x-badge>

                    @if ($strategy->allowsManualCompletion())
                        <button type="button" wire:click="toggleComplete" wire:loading.attr="disabled"
                                class="text-sm text-neutral-500 underline-offset-4 transition-colors hover:text-neutral-800 hover:underline">
                            Mark as not complete
                        </button>
                    @endif
                @elseif ($strategy->allowsManualCompletion())
                    <button type="button" wire:click="toggleComplete" wire:loading.attr="disabled"
                            class="rounded-sm bg-teal-600 px-5 py-[11px] text-sm font-semibold text-white transition-colors hover:bg-teal-700">
                        Mark complete
                    </button>
                @endif
            </div>
        </div>

        @if ($lesson->summary)
            <p class="mt-4 max-w-[68ch] text-[15.5px]/[1.65] text-neutral-700">{{ $lesson->summary }}</p>
        @endif

        {{-- The completion rule, stated where a button would otherwise be. --}}
        @if (! $done && ! $strategy->allowsManualCompletion())
            <p class="mt-4 text-sm text-neutral-500">
                @if ($strategy === \App\Enums\CompletionStrategy::VideoThreshold)
                    This lesson completes once you have watched {{ $videoThreshold }}% of the video.
                @else
                    This lesson completes when you pass its assessment.
                @endif
            </p>
        @endif

        {{-- ══ NAVIGATION ══ --}}
        <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
            <div>
                @if ($neighbours['previous'])
                    <a href="{{ route('student.courses.play', [$course, $neighbours['previous']]) }}"
                       class="rounded-sm border border-neutral-300 bg-white px-5 py-[11px] text-sm font-semibold text-neutral-700 transition-colors hover:bg-neutral-50">
                        ← Previous lesson
                    </a>
                @endif
            </div>

            <div>
                @if ($neighbours['next'])
                    <a href="{{ route('student.courses.play', [$course, $neighbours['next']]) }}"
                       class="rounded-sm bg-teal-600 px-5 py-[11px] text-sm font-semibold text-white transition-colors hover:bg-teal-700">
                        Next lesson →
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- ══ CURRICULUM RAIL ══ sticks below the header and scrolls on its own,
         so the contents list stays reachable through a long lesson. --}}
    <aside class="border-neutral-200 bg-white lg:sticky lg:top-16 lg:block lg:h-[calc(100vh-4rem)] lg:overflow-y-auto lg:border-l"
           x-bind:class="curriculum ? '' : 'hidden lg:block'"
           aria-label="Course curriculum">

        {{-- Course figure, counted from the curriculum on screen so it can never
             disagree with the ticks below it. --}}
        <div class="border-b border-neutral-200 px-[22px] py-5">
            <p class="mb-3 font-mono text-[11px] font-semibold tracking-[0.14em] text-neutral-700">COURSE CONTENT</p>

            <div class="flex items-center gap-2.5">
                <x-progress-bar :value="$percentage" class="flex-1" />
                <span class="font-mono text-xs font-semibold text-neutral-700">{{ $percentage }}%</span>
            </div>

            <p class="mt-1.5 text-[12.5px] text-neutral-500">
                {{ $completedCount }} of {{ $totalCount }} {{ Str::plural('lesson', $totalCount) }} completed
            </p>
        </div>

        @foreach ($curriculum as $module)
            @php $figures = $moduleProgress[$module->id] ?? ['completed' => 0, 'total' => 0, 'percentage' => 0]; @endphp

            <div wire:key="module-{{ $module->id }}">
                <div class="px-[22px] pb-2 pt-3.5">
                    <p class="text-[12.5px] font-semibold text-neutral-500">{{ $module->title }}</p>

                    {{-- Module progress is DERIVED, never stored (ADR-008) —
                         computed from the lessons and progress this page has
                         already loaded, so the rail costs no extra queries
                         however many modules a course has. --}}
                    @if ($figures['total'] > 0)
                        <p class="mt-0.5 font-mono text-[11px] tracking-[0.04em] text-neutral-400">
                            {{ $figures['completed'] }} / {{ $figures['total'] }}
                        </p>
                    @endif
                </div>

                <ul>
                    @foreach ($module->lessons as $item)
                        @php
                            $itemDone = ($progress[$item->id] ?? null)?->completed_at !== null;
                            $current = $item->id === $lesson->id;
                        @endphp
                        <li>
                            <a href="{{ route('student.courses.play', [$course, $item]) }}"
                               @if ($current) aria-current="page" @endif
                               class="flex items-center gap-[11px] border-l-[3px] px-[22px] py-2.5 text-[13.5px] transition-colors {{ $current ? 'border-teal-600 bg-teal-50 text-neutral-900' : 'border-transparent text-neutral-700 hover:bg-neutral-50' }}">

                                {{-- Completion is stated in text for screen
                                     readers, not by colour alone (WCAG 1.4.1). --}}
                                <span class="flex w-4 shrink-0 justify-center" aria-hidden="true">
                                    @if ($itemDone)
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                                             class="text-honeydew"><path d="M20 6 9 17l-5-5"></path></svg>
                                    @elseif ($current)
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"
                                             class="text-teal-600"><path d="M6 4l14 8-14 8z"></path></svg>
                                    @else
                                        <span class="inline-block size-2 rounded-full border-[1.5px] border-neutral-400"></span>
                                    @endif
                                </span>

                                <span class="min-w-0 flex-1">
                                    {{ $item->title }}
                                    @if ($itemDone)
                                        <span class="sr-only">(completed)</span>
                                    @endif
                                </span>

                                @if ($item->duration_seconds)
                                    <span class="font-mono text-[11.5px] text-neutral-400">
                                        {{ intdiv($item->duration_seconds, 60) }}m
                                    </span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </aside>
</div>
