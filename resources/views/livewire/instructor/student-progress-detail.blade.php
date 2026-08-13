<div>
    <div class="mb-6 flex items-center gap-3">
        <h1 class="flex-1 text-2xl">{{ $enrollment->user?->name }}</h1>
        <x-badge :variant="$enrollment->status->badgeVariant()">{{ $enrollment->status->label() }}</x-badge>
    </div>

    <div class="mb-6 rounded-card border border-neutral-200 bg-white p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <p class="eyebrow text-neutral-500">Course progress</p>
            <p class="font-serif text-2xl font-semibold tracking-[-0.015em] text-neutral-900">{{ $enrollment->progress_percentage }}%</p>
        </div>
        <x-progress-bar :value="$enrollment->progress_percentage" class="mt-3" />
        <p class="mt-3 text-sm text-neutral-500">
            {{ $enrollment->completed_lessons_count }} {{ Str::plural('lesson', $enrollment->completed_lessons_count) }} completed.
            @if ($enrollment->completed_at)
                Finished the course on {{ $enrollment->completed_at->format('d M Y') }}.
            @endif
        </p>
    </div>

    @if ($curriculum->isEmpty())
        <x-empty-state title="No published lessons yet" description="Once this course has published modules and lessons, per-lesson progress appears here." />
    @else
        <div class="overflow-hidden rounded-card border border-neutral-200 bg-white">
            @foreach ($curriculum as $module)
                <div class="border-b border-neutral-100 last:border-b-0">
                    @php($figures = $moduleProgress[$module->id] ?? ['completed' => 0, 'total' => 0, 'percentage' => 0])

                    <div class="bg-neutral-50 px-4 py-3">
                        <p class="eyebrow text-neutral-500">Module {{ $loop->iteration }}</p>
                        <p class="mt-1 font-serif text-base font-semibold text-neutral-900">{{ $module->title }}</p>

                        @if ($figures['total'] > 0)
                            <p class="mt-2 font-mono text-[11px] tracking-[0.04em] text-neutral-500">
                                {{ $figures['completed'] }} / {{ $figures['total'] }} LESSONS
                            </p>
                            <x-progress-bar :value="$figures['percentage']" size="sm" class="mt-1.5" />
                        @endif
                    </div>

                    <ul>
                        @foreach ($module->lessons as $item)
                            @php($done = ($progress[$item->id] ?? null)?->completed_at !== null)
                            <li class="flex items-start gap-3 border-t border-neutral-100 px-4 py-3 text-sm first:border-t-0">
                                <span class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border {{ $done ? 'border-teal-600 bg-teal-600' : 'border-neutral-300' }}">
                                    @if ($done)
                                        <svg class="size-2.5 text-white" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M2 5.5L4 7.5L8 3" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    @endif
                                </span>

                                <span class="min-w-0 flex-1 text-neutral-700">
                                    {{ $item->title }}
                                    @if ($done)
                                        <span class="sr-only">(completed)</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif
</div>
