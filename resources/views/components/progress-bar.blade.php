@props([
    'value' => 0,
    'label' => null,
    'size' => 'md',
    'tone' => 'brand',
])

{{--
    A progress bar (FR-PROG-06, FR-PROG-07).

    ONE COMPONENT, FOUR PLACES: course card, player header, module header and
    the dashboard summary. Phase 9 needed a bar in all four, and four
    hand-rolled bars is how a design system starts disagreeing with itself
    (docs/UI-GUIDE.md §8 — extend, never fork).

    ═════════════════════════════════════════════════════════════════════════
    THE BAR IS EITHER LABELLED OR HIDDEN, NEVER BOTH AND NEVER NEITHER.

    Pass `label` where the bar is the only statement of the figure: it becomes
    a real progressbar with its value announced. Omit it where the percentage
    is already written in text beside it — then the bar is decorative and
    aria-hidden, because a screen reader announcing the same number twice is
    noise, and colour alone would be the only signal without that text
    (WCAG 1.4.1).
    ═════════════════════════════════════════════════════════════════════════

    The width is the exact percentage. A minimum sliver at 0% would draw
    progress that does not exist, and "you have started" is not a claim a
    progress bar gets to make on the student's behalf.
--}}

@php
    // Clamped rather than trusted. A caller computing 103% from a stale
    // denominator should get a full bar, not one that overflows its track.
    $percent = max(0, min(100, (int) $value));

    $track = $size === 'sm' ? 'h-1' : 'h-1.5';

    $fill = match ($tone) {
        'success' => 'bg-success-600',
        'inverse' => 'bg-white',
        default => 'bg-teal-600',
    };

    $groove = $tone === 'inverse' ? 'bg-white/20' : 'bg-neutral-100';
@endphp

<div
    {{ $attributes->class(['overflow-hidden rounded-full', $track, $groove]) }}
    @if ($label)
        role="progressbar"
        aria-valuenow="{{ $percent }}"
        aria-valuemin="0"
        aria-valuemax="100"
        aria-label="{{ $label }}"
    @else
        aria-hidden="true"
    @endif
>
    <div class="h-full rounded-full transition-[width] duration-500 {{ $fill }}"
         style="width: {{ $percent }}%"></div>
</div>
