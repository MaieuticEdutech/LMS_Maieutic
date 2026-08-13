<div>
    <div class="mb-4 flex flex-wrap items-end gap-4">
        <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Search action or description" class="max-w-xs" />

        <x-select wire:model.live="actionFilter" name="actionFilter" label="Action" placeholder="All actions" class="max-w-xs">
            @foreach ($actionOptions as $action)
                <option value="{{ $action }}">{{ $action }}</option>
            @endforeach
        </x-select>
    </div>

    @if ($entries->isEmpty())
        <x-empty-state
            title="No audit entries yet"
            description="Security- and money-relevant actions appear here as they happen — logins, role changes, publishing, enrollment grants and more."
        />
    @else
        <x-table caption="Audit log">
            <x-slot:head>
                <th class="px-3 py-2">
                    <button type="button" wire:click="sortBy('created_at')" class="font-semibold uppercase tracking-wide">When</button>
                </th>
                <th class="px-3 py-2">Actor</th>
                <th class="px-3 py-2">Action</th>
                <th class="px-3 py-2">Description</th>
                <th class="px-3 py-2">Changes</th>
            </x-slot:head>

            @foreach ($entries as $entry)
                <tr wire:key="audit-log-{{ $entry->id }}">
                    <td class="whitespace-nowrap px-3 py-2 text-neutral-500">{{ $entry->created_at?->format('d M Y, H:i') }}</td>
                    <td class="px-3 py-2 font-medium text-neutral-900">{{ $entry->user?->name ?? 'System' }}</td>
                    <td class="px-3 py-2 font-mono text-xs text-neutral-600">{{ $entry->action }}</td>
                    <td class="max-w-md px-3 py-2 text-neutral-500">{{ $entry->description }}</td>
                    <td class="px-3 py-2 text-neutral-500">
                        @if ($entry->changes)
                            <details>
                                <summary class="cursor-pointer text-teal-600 hover:underline">View details</summary>
                                <pre class="mt-1 max-w-md overflow-x-auto whitespace-pre-wrap text-xs text-neutral-500">{{ json_encode($entry->changes, JSON_PRETTY_PRINT) }}</pre>
                            </details>
                        @else
                            <span class="text-neutral-400">&mdash;</span>
                        @endif
                    </td>
                </tr>
            @endforeach

            <x-slot:pagination>
                {{ $entries->links() }}
            </x-slot:pagination>
        </x-table>
    @endif
</div>
