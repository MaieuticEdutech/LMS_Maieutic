<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1>Courses</h1>
            <p class="mt-1 text-sm text-neutral-500">
                {{ $statusCounts['all'] }} {{ Str::plural('course', $statusCounts['all']) }}
                @foreach ($statusOptions as $status)
                    &middot; {{ $statusCounts[$status->value] ?? 0 }} {{ Str::lower($status->label()) }}
                @endforeach
            </p>
        </div>

        <x-button :href="route('admin.courses.create')" wire:navigate>Create course</x-button>
    </div>

    <div class="mb-5 flex flex-wrap items-center gap-4">
        <div class="flex gap-5 border-b border-neutral-200">
            <button
                type="button"
                wire:click="$set('statusFilter', '')"
                class="border-b-2 pb-2.5 text-sm font-medium {{ $statusFilter === '' ? 'border-teal-600 text-neutral-900' : 'border-transparent text-neutral-500 hover:text-neutral-800' }}"
            >
                All
            </button>
            @foreach ($statusOptions as $status)
                <button
                    type="button"
                    wire:click="$set('statusFilter', '{{ $status->value }}')"
                    class="border-b-2 pb-2.5 text-sm font-medium {{ $statusFilter === $status->value ? 'border-teal-600 text-neutral-900' : 'border-transparent text-neutral-500 hover:text-neutral-800' }}"
                >
                    {{ $status->label() }}
                </button>
            @endforeach
        </div>

        <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Search by title" class="ml-auto max-w-xs" />
    </div>

    <div wire:loading.class="opacity-60" class="transition-opacity">
        @if ($courses->isEmpty())
            <x-empty-state
                title="No courses yet"
                description="Build your first course to give students something to enrol in."
            >
                <x-slot:action>
                    <x-button :href="route('admin.courses.create')" wire:navigate>Create course</x-button>
                </x-slot:action>
            </x-empty-state>
        @else
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                @foreach ($courses as $course)
                    <div wire:key="course-{{ $course->id }}" class="flex items-center gap-4 rounded-card border border-neutral-200 bg-white p-4 transition-colors hover:border-neutral-300">
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
                            <div class="text-xs text-neutral-500">{{ $course->category?->name ?? 'Uncategorised' }} &middot; {{ $course->created_at?->format('d M Y') }}</div>
                            <div class="mt-0.5 text-xs text-neutral-500">
                                <span class="font-medium text-neutral-900">{{ $course->price }}</span>
                            </div>
                        </div>

                        <x-button :href="route('admin.courses.builder', $course)" variant="secondary" size="sm" wire:navigate>
                            {{ $course->status === \App\Enums\CourseStatus::Draft ? 'Continue building' : 'Open builder' }}
                        </x-button>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $courses->links() }}
            </div>
        @endif
    </div>
</div>
