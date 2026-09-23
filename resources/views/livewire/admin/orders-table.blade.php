<div>
    <div class="mb-4 flex flex-wrap items-end gap-4">
        <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Search order number, name or email" class="max-w-xs" />

        <x-select wire:model.live="statusFilter" name="statusFilter" label="Status" placeholder="All statuses" class="max-w-xs">
            @foreach ($statusOptions as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
            @endforeach
        </x-select>
    </div>

    @if ($orders->isEmpty())
        <x-empty-state
            title="No orders yet"
            description="Every checkout attempt appears here once a buyer starts it — pending ones are still in progress, paid ones have granted access."
        />
    @else
        <x-table caption="Orders">
            <x-slot:head>
                <th class="px-3 py-2">
                    <button type="button" wire:click="sortBy('placed_at')" class="font-semibold uppercase tracking-wide">Placed</button>
                </th>
                <th class="px-3 py-2">Order</th>
                <th class="px-3 py-2">Buyer</th>
                <th class="px-3 py-2">Course</th>
                <th class="px-3 py-2">Amount</th>
                <th class="px-3 py-2">Status</th>
            </x-slot:head>

            @foreach ($orders as $order)
                <tr wire:key="order-{{ $order->id }}">
                    <td class="whitespace-nowrap px-3 py-2 text-neutral-500">{{ $order->placed_at?->format('d M Y, H:i') }}</td>
                    <td class="px-3 py-2">
                        <a href="{{ route('admin.orders.show', $order) }}" class="font-mono text-xs text-teal-600 hover:underline" wire:navigate>
                            {{ $order->order_number }}
                        </a>
                    </td>
                    <td class="px-3 py-2">
                        <div class="font-medium text-neutral-900">{{ $order->buyer_name }}</div>
                        <div class="text-xs text-neutral-500">{{ $order->buyer_email }}</div>
                    </td>
                    <td class="max-w-xs truncate px-3 py-2 text-neutral-500">{{ $order->course?->title ?? '—' }}</td>
                    <td class="whitespace-nowrap px-3 py-2 font-medium text-neutral-900">{{ $order->total }}</td>
                    <td class="px-3 py-2">
                        <x-badge :variant="$order->status->badgeVariant()">{{ $order->status->label() }}</x-badge>
                    </td>
                </tr>
            @endforeach

            <x-slot:pagination>
                {{ $orders->links() }}
            </x-slot:pagination>
        </x-table>
    @endif
</div>
