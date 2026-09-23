{{--
    Webhook event log (architecture.md §13).

    The screen someone opens when a payment appears stuck. It answers: did
    Razorpay's webhook ever arrive, and what happened to it once it did —
    received, settled, ignored, or failed after every retry.
--}}
<div>
    <div class="mb-4 flex flex-wrap items-end gap-4">
        <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Search event id or type" class="max-w-xs" />

        <x-select wire:model.live="statusFilter" name="statusFilter" label="Status" placeholder="All statuses" class="max-w-xs">
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}">{{ ucfirst($status->value) }}</option>
            @endforeach
        </x-select>

        @if ($statusFilter === '')
            <button type="button" wire:click="showFailuresOnly" class="text-sm font-medium text-red-600 hover:underline">
                Show failures only
            </button>
        @endif
    </div>

    @if ($entries->isEmpty())
        <x-empty-state
            title="No webhook deliveries yet"
            description="Every signature-verified delivery from the payment gateway is recorded here as it arrives, whether or not this application acted on it."
        />
    @else
        <x-table caption="Webhook events">
            <x-slot:head>
                <th class="px-3 py-2">
                    <button type="button" wire:click="sortBy('received_at')" class="font-semibold uppercase tracking-wide">Received</button>
                </th>
                <th class="px-3 py-2">Event</th>
                <th class="px-3 py-2">Event id</th>
                <th class="px-3 py-2">Attempts</th>
                <th class="px-3 py-2">
                    <button type="button" wire:click="sortBy('status')" class="font-semibold uppercase tracking-wide">Status</button>
                </th>
            </x-slot:head>

            @foreach ($entries as $entry)
                <tr wire:key="webhook-event-{{ $entry->id }}">
                    <td class="whitespace-nowrap px-3 py-2 text-neutral-500">{{ $entry->received_at->format('d M Y, H:i') }}</td>
                    <td class="px-3 py-2 font-mono text-xs text-neutral-600">{{ $entry->event_type }}</td>
                    <td class="max-w-xs truncate px-3 py-2 font-mono text-xs text-neutral-600" title="{{ $entry->event_id }}">{{ $entry->event_id }}</td>
                    <td class="px-3 py-2 text-neutral-500">{{ $entry->attempts }}</td>
                    <td class="px-3 py-2">
                        @if ($entry->status === \App\Enums\WebhookStatus::Processed)
                            <x-badge variant="success">Processed</x-badge>
                        @elseif ($entry->status === \App\Enums\WebhookStatus::Failed)
                            <x-badge variant="danger">Failed</x-badge>
                        @elseif ($entry->status === \App\Enums\WebhookStatus::Ignored)
                            <x-badge variant="neutral">Ignored</x-badge>
                        @else
                            <x-badge variant="warning">{{ ucfirst($entry->status->value) }}</x-badge>
                        @endif

                        @if ($entry->last_error)
                            <details class="mt-1">
                                <summary class="cursor-pointer text-xs text-teal-600 hover:underline">Why it failed</summary>
                                <pre class="mt-1 max-w-md overflow-x-auto whitespace-pre-wrap text-xs text-neutral-500">{{ $entry->last_error }}</pre>
                            </details>
                        @endif

                        <details class="mt-1">
                            <summary class="cursor-pointer text-xs text-teal-600 hover:underline">Raw payload</summary>
                            <pre class="mt-1 max-w-md overflow-x-auto whitespace-pre-wrap text-xs text-neutral-500">{{ json_encode($entry->payload, JSON_PRETTY_PRINT) }}</pre>
                        </details>
                    </td>
                </tr>
            @endforeach

            <x-slot:pagination>
                {{ $entries->links() }}
            </x-slot:pagination>
        </x-table>
    @endif
</div>
