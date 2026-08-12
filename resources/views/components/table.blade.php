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
--}}

<div class="overflow-x-auto rounded-card ring-1 ring-zinc-200">
    <table {{ $attributes->class('min-w-full divide-y divide-zinc-200 bg-white text-sm') }}>
        @if ($caption)
            <caption class="sr-only">{{ $caption }}</caption>
        @endif

        @isset($head)
            <thead class="bg-zinc-50">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
                    {{ $head }}
                </tr>
            </thead>
        @endisset

        <tbody class="divide-y divide-zinc-100">
            {{ $slot }}
        </tbody>
    </table>
</div>

@isset($pagination)
    <div class="mt-3">
        {{ $pagination }}
    </div>
@endisset
