<div>
    <div class="mb-4 flex flex-wrap items-end gap-4">
        <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Search by title" class="max-w-xs" />

        <x-select wire:model.live="statusFilter" name="statusFilter" label="Status" placeholder="All statuses" class="max-w-xs">
            @foreach ($statusOptions as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
            @endforeach
        </x-select>
    </div>

    @if ($courses->isEmpty())
        <x-empty-state
            title="No courses yet"
            description="Courses appear here once they are created. Course creation and editing arrive in Phase 5."
        />
    @else
        <x-table caption="Courses">
            <x-slot:head>
                <th class="px-3 py-2">
                    <button type="button" wire:click="sortBy('title')" class="font-semibold uppercase tracking-wide">Title</button>
                </th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Category</th>
                <th class="px-3 py-2">Created</th>
            </x-slot:head>

            @foreach ($courses as $course)
                <tr wire:key="course-{{ $course->id }}">
                    <td class="px-3 py-2 font-medium text-zinc-900">{{ $course->title }}</td>
                    <td class="px-3 py-2"><x-badge :variant="$course->status->badgeVariant()">{{ $course->status->label() }}</x-badge></td>
                    <td class="px-3 py-2 text-zinc-500">{{ $course->category?->name ?? 'Uncategorised' }}</td>
                    <td class="px-3 py-2 text-zinc-500">{{ $course->created_at?->format('d M Y') }}</td>
                </tr>
            @endforeach

            <x-slot:pagination>
                {{ $courses->links() }}
            </x-slot:pagination>
        </x-table>
    @endif
</div>
