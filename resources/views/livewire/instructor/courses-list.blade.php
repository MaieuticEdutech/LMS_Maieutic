<div>
    <h1 class="mb-6">My courses</h1>

    @if ($courses->isEmpty())
        <x-empty-state
            title="No courses assigned yet"
            description="Once a Super Admin assigns you to a course, it appears here."
        />
    @else
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($courses as $course)
                <div wire:key="course-{{ $course->id }}" class="flex items-center gap-4 rounded-card border border-neutral-200 bg-white p-4">
                    <div class="relative h-16 w-24 shrink-0 overflow-hidden rounded-md bg-teal-50">
                        <span class="absolute bottom-1 left-2 font-serif text-2xl text-teal-700/50">
                            {{ Str::substr($course->title, 0, 1) }}
                        </span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="mb-0.5 flex items-center gap-2">
                            <span class="truncate font-semibold text-neutral-900">{{ $course->title }}</span>
                            <x-badge :variant="$course->status->badgeVariant()">{{ $course->status->label() }}</x-badge>
                        </div>
                        <div class="text-xs text-neutral-500">{{ $course->modules_count }} {{ Str::plural('module', $course->modules_count) }} &middot; {{ $course->lessons_count }} {{ Str::plural('lesson', $course->lessons_count) }}</div>

                        @php($averageProgress = (int) round($course->enrollments_avg_progress_percentage ?? 0))
                        <div class="mt-2 flex items-center gap-2">
                            <x-progress-bar :value="$averageProgress" size="sm" class="max-w-[8rem]" />
                            <span class="font-mono text-xs text-neutral-500">{{ $averageProgress }}% avg progress</span>
                        </div>
                    </div>

                    <x-button :href="route('instructor.courses.show', $course)" variant="secondary" size="sm" wire:navigate>
                        View
                    </x-button>
                </div>
            @endforeach
        </div>
    @endif
</div>
