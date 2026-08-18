{{--
    The filter bar every report shares (FR-RPT-06).

    Both dates are URL-bound, so a filtered report can be sent to a colleague
    as a link and open on the same figures. The period is restated in words
    beside the export button because "1 Mar – 31 Mar" on screen is what stops
    somebody exporting the wrong window and mailing it onward.
--}}
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div class="flex flex-wrap items-end gap-3">
        <x-input wire:model.live="from" type="date" name="from" label="From" class="max-w-[11rem]" />
        <x-input wire:model.live="to" type="date" name="to" label="To" class="max-w-[11rem]" />

        {{ $slot ?? '' }}

        @if ($from !== '' || $to !== '')
            <x-button variant="secondary" wire:click="clearFilters">Clear dates</x-button>
        @endif
    </div>

    <div class="flex items-center gap-3">
        <span class="font-mono text-xs uppercase tracking-[0.08em] text-neutral-500">{{ $range->label() }}</span>
        <x-button wire:click="export">Export CSV</x-button>
    </div>
</div>
