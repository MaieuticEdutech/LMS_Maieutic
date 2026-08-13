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

        <p class="shrink-0 font-mono text-xs tracking-[0.04em] text-neutral-500">
            {{ $completedCount }} / {{ $totalCount }} COMPLETE
        </p>
    </div>

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
                        <div class="bg-neutral-50 px-4 py-3">
                            <p class="eyebrow text-neutral-500">Module {{ $loop->iteration }}</p>
                            <p class="mt-1 font-serif text-base font-semibold text-neutral-900">{{ $module->title }}</p>
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
                <x-button wire:click="toggleComplete"
                          :variant="$lessonProgress?->completed_at !== null ? 'secondary' : 'primary'"
                          wire:loading.attr="disabled">
                    {{ $lessonProgress?->completed_at !== null ? 'Completed' : 'Mark as complete' }}
                </x-button>

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
