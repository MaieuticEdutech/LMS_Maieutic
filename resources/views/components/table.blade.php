@props([
    'caption' => null,
])

{{--
    Data table shell.

    NFR-PERF-02: every list rendered in this component MUST be server-side
    paginated. There is no "show all" mode, in any phase — an unbounded query
    is prohibited regardless of how small the dataset looks today.

    Wide tables scroll inside their own container so the page body never
    scrolls horizontally on mobile (NFR-UX-01).

    STYLING (docs/UI-GUIDE.md §8): hairline row separators, no zebra striping,
    no vertical rules. Column headers are small tracked mono — the same device
    as the eyebrow, which is what keeps a dense table looking like part of an
    editorial system rather than a spreadsheet.
--}}

<div class="overflow-x-auto rounded-card border border-neutral-200">
    <table {{ $attributes->class('min-w-full divide-y divide-neutral-200 bg-white text-sm') }}>
        @if ($caption)
            <caption class="sr-only">{{ $caption }}</caption>
        @endif

        @isset($head)
            <thead class="bg-neutral-50">
                <tr class="text-left font-mono text-[11px] font-medium uppercase tracking-[0.1em] text-neutral-500">
                    {{ $head }}
                </tr>
            </thead>
        @endisset

        {{-- Lighter divider inside the body than under the header: the header
             separation should read stronger than any row boundary. --}}
        <tbody class="divide-y divide-neutral-100 text-neutral-800">
            {{ $slot }}
        </tbody>
    </table>
</div>

@isset($pagination)
    <div class="mt-4">
        {{ $pagination }}
    </div>
@endisset
