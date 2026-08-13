<div class="mx-auto max-w-content px-6 py-12">
    <a href="{{ route('catalogue.index') }}" wire:navigate class="mb-5 inline-flex items-center gap-1.5 text-sm font-semibold text-neutral-700 hover:text-teal-700">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
        Back to catalogue
    </a>

    <div class="grid grid-cols-1 items-start gap-8 lg:grid-cols-[1fr_var(--spacing-detail-aside)]">
        <div>
            <p class="eyebrow text-teal-600">{{ $course->category?->name ?? 'General' }} &middot; {{ $course->level->label() }}</p>
            <h1 class="mt-2.5 text-4xl">{{ $course->title }}</h1>

            @if ($course->description)
                <p class="mt-3 max-w-measure text-base leading-relaxed text-neutral-700">{{ $course->description }}</p>
            @endif

            @if ($course->instructors->isNotEmpty())
                <div class="mt-5 flex flex-wrap gap-4">
                    @foreach ($course->instructors as $instructor)
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-neutral-200 text-xs font-semibold text-neutral-700">
                                {{ Str::of($instructor->name)->explode(' ')->map(fn ($p) => Str::substr($p, 0, 1))->take(2)->join('') }}
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-neutral-900">{{ $instructor->name }}</div>
                                @if ($instructor->instructorProfile?->headline)
                                    <div class="text-xs text-neutral-500">{{ $instructor->instructorProfile->headline }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if (! empty($course->outcomes))
                <h2 class="mt-9 text-2xl">What you'll learn</h2>
                <div class="mt-3.5 grid grid-cols-1 gap-x-6 gap-y-2.5 sm:grid-cols-2">
                    @foreach ($course->outcomes as $outcome)
                        <div class="flex items-start gap-2.5 text-sm text-neutral-800">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0 text-teal-600" aria-hidden="true"><path d="M20 6 9 17l-5-5"></path></svg>
                            <span>{{ $outcome }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            @if (! empty($course->requirements))
                <h2 class="mt-9 text-2xl">Requirements</h2>
                <ul class="mt-3.5 list-inside list-disc space-y-1 text-sm text-neutral-800">
                    @foreach ($course->requirements as $requirement)
                        <li>{{ $requirement }}</li>
                    @endforeach
                </ul>
            @endif

            <h2 class="mt-9 text-2xl">Course structure</h2>
            <div class="mt-3.5 overflow-hidden rounded-md border border-neutral-200 bg-white">
                @forelse ($curriculum as $module)
                    <div class="border-t border-neutral-200 first:border-t-0">
                        <div class="flex items-center gap-3 bg-neutral-50 px-4 py-3">
                            <span class="font-mono text-xs text-neutral-500">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="flex-1 font-semibold text-neutral-900">{{ $module->title }}</span>
                            <span class="text-xs text-neutral-500">{{ $module->lessons->count() }} {{ Str::plural('lesson', $module->lessons->count()) }}</span>
                        </div>
                        @foreach ($module->lessons as $lesson)
                            <div class="flex items-center gap-3 border-t border-neutral-100 px-4 py-2.5 pl-11 text-sm">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" class="shrink-0 text-neutral-400" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                <span class="flex-1 text-neutral-700">{{ $lesson->title }}</span>
                                @if ($lesson->duration_seconds)
                                    <span class="font-mono text-xs text-neutral-400">{{ intdiv($lesson->duration_seconds, 60) }}m</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-neutral-500">Curriculum coming soon.</p>
                @endforelse
            </div>
        </div>

        <div class="sticky top-6 overflow-hidden rounded-card border border-neutral-200 bg-white shadow-sm">
            <div class="relative h-36 overflow-hidden bg-teal-50">
                <span class="absolute bottom-3 left-4 font-serif text-5xl text-teal-700/50">
                    {{ Str::substr($course->title, 0, 1) }}
                </span>
            </div>
            <div class="px-5 py-5">
                <div class="mb-4 font-serif text-3xl font-semibold text-neutral-900">{{ $course->price }}</div>

                <x-button variant="primary" size="lg" class="w-full" disabled>Purchase course</x-button>
                <p class="mb-4 mt-2.5 text-center text-xs text-neutral-500">Enrolment opens soon.</p>

                <div class="space-y-2 border-t border-neutral-200 pt-4 text-sm text-neutral-800">
                    <div class="flex justify-between"><span class="text-neutral-500">Modules</span><span class="font-semibold">{{ $course->modules_count }}</span></div>
                    <div class="flex justify-between"><span class="text-neutral-500">Lessons</span><span class="font-semibold">{{ $course->lessons_count }}</span></div>
                    @if ($course->total_duration_seconds > 0)
                        <div class="flex justify-between"><span class="text-neutral-500">Video</span><span class="font-semibold">{{ intdiv($course->total_duration_seconds, 3600) }}h {{ intdiv($course->total_duration_seconds % 3600, 60) }}m</span></div>
                    @endif
                    <div class="flex justify-between"><span class="text-neutral-500">Level</span><span class="font-semibold">{{ $course->level->label() }}</span></div>
                    @if ($course->requires_final_test)
                        <div class="flex justify-between"><span class="text-neutral-500">Certificate</span><span class="font-semibold">On completion</span></div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
