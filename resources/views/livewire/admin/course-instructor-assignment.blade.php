<div>
    <x-card title="Assigned courses">
        @if ($assignedCourses->isEmpty())
            <p class="text-sm text-zinc-500">Not assigned to any courses yet.</p>
        @else
            <ul class="divide-y divide-zinc-100">
                @foreach ($assignedCourses as $course)
                    <li wire:key="assigned-course-{{ $course->id }}" class="flex items-center justify-between py-2">
                        <span class="text-sm font-medium text-zinc-900">{{ $course->title }}</span>

                        @can('manageInstructors', $course)
                            <x-button wire:click="unassign({{ $course->id }})" wire:confirm="Unassign this instructor from &quot;{{ $course->title }}&quot;?" variant="secondary" size="sm">
                                Unassign
                            </x-button>
                        @endcan
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($availableCourses->isNotEmpty())
            <div class="mt-4 flex items-end gap-2 border-t border-zinc-200 pt-4">
                <div class="flex-1">
                    <x-select wire:model="courseToAssign" label="Assign to a course" placeholder="Select a course&hellip;">
                        @foreach ($availableCourses as $course)
                            <option value="{{ $course->id }}">{{ $course->title }}</option>
                        @endforeach
                    </x-select>
                </div>
                <x-button wire:click="assign" variant="primary" size="sm">Assign</x-button>
            </div>
        @endif
    </x-card>
</div>
