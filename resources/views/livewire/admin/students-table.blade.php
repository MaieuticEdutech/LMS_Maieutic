<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <div class="flex flex-wrap items-end gap-3">
            <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Search by name or email" class="max-w-xs" />

            <x-select wire:model.live="statusFilter" name="statusFilter" label="Status" class="max-w-[12rem]">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </x-select>

            @if ($search !== '' || $statusFilter !== '' || $sortField !== null)
                <x-button variant="secondary" wire:click="resetTableFilters">Clear filters</x-button>
            @endif
        </div>

        @can('create', App\Models\User::class)
            <x-button :href="route('admin.students.create')" wire:navigate>Add student</x-button>
        @endcan
    </div>

    @if ($students->isEmpty())
        <x-empty-state
            title="No students yet"
            description="Students appear here once they register or an admin creates an account for them."
        />
    @else
        <x-table>
            <x-slot:head>
                <th class="px-3 py-2">
                    <button type="button" wire:click="sortBy('name')" class="font-semibold uppercase tracking-wide">Name</button>
                </th>
                <th class="px-3 py-2">Email</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Joined</th>
                <th class="px-3 py-2"><span class="sr-only">Actions</span></th>
            </x-slot:head>

            @foreach ($students as $student)
                <tr wire:key="student-{{ $student->id }}">
                    <td class="px-3 py-2 font-medium text-neutral-900">{{ $student->name }}</td>
                    <td class="px-3 py-2 text-neutral-500">{{ $student->email }}</td>
                    <td class="px-3 py-2"><x-badge :variant="$student->status->badgeVariant()">{{ $student->status->label() }}</x-badge></td>
                    <td class="px-3 py-2 text-neutral-500">{{ $student->created_at?->format('d M Y') }}</td>
                    <td class="px-3 py-2 text-right">
                        <a href="{{ route('admin.students.show', $student) }}" wire:navigate class="text-teal-600 hover:underline">View</a>
                    </td>
                </tr>
            @endforeach

            <x-slot:pagination>
                {{ $students->links() }}
            </x-slot:pagination>
        </x-table>
    @endif
</div>
