<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Search by name or email" class="max-w-xs" />

        @can('create', App\Models\User::class)
            <x-button :href="route('admin.instructors.create')" wire:navigate>Add instructor</x-button>
        @endcan
    </div>

    @if ($instructors->isEmpty())
        <x-empty-state
            title="No instructors yet"
            description="Instructors appear here once an admin creates an account for them."
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

            @foreach ($instructors as $instructor)
                <tr wire:key="instructor-{{ $instructor->id }}">
                    <td class="px-3 py-2 font-medium text-neutral-900">{{ $instructor->name }}</td>
                    <td class="px-3 py-2 text-neutral-500">{{ $instructor->email }}</td>
                    <td class="px-3 py-2"><x-badge :variant="$instructor->status->badgeVariant()">{{ $instructor->status->label() }}</x-badge></td>
                    <td class="px-3 py-2 text-neutral-500">{{ $instructor->created_at?->format('d M Y') }}</td>
                    <td class="px-3 py-2 text-right">
                        <a href="{{ route('admin.instructors.show', $instructor) }}" wire:navigate class="text-teal-600 hover:underline">View</a>
                    </td>
                </tr>
            @endforeach

            <x-slot:pagination>
                {{ $instructors->links() }}
            </x-slot:pagination>
        </x-table>
    @endif
</div>
