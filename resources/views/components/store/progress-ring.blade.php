@props([
    // 0-100, from the cached enrolment figures. The caller passes it; this
    // component never computes progress.
    'value' => 0,
    'size' => 96,
    'label' => null,
])

@php
    $value = max(0, min(100, (int) $value));
    $radius = 42;
    $circumference = 2 * M_PI * $radius;
    $offset = $circumference * (1 - $value / 100);
    // Scale the figure with the ring: a fixed 26px made "100%" wider than a
    // 64px ring. Three digits plus the sign need a smaller share of the ring.
    $fontSize = (int) round($size * ($value >= 100 ? 0.23 : 0.27));
@endphp

{{-- A circular progress figure. The percentage is written in the middle, so
     the ring itself is decorative. --}}
<div {{ $attributes->class('relative inline-grid shrink-0 place-items-center') }} style="width: {{ $size }}px; height: {{ $size }}px">
    <svg viewBox="0 0 100 100" class="absolute inset-0 -rotate-90" aria-hidden="true">
        <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke="currentColor" stroke-width="9" class="text-teal-50"/>
        <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke="currentColor" stroke-width="9" stroke-linecap="round"
                stroke-dasharray="{{ round($circumference, 2) }}" stroke-dashoffset="{{ round($offset, 2) }}" class="text-teal-600"/>
    </svg>
    <span class="relative text-center leading-none">
        <b class="font-serif font-medium text-neutral-900 lining-nums" style="font-size: {{ $fontSize }}px">{{ $value }}%</b>
        @if ($label)
            <span class="mt-1 block text-[10.5px] font-semibold uppercase tracking-wide text-neutral-500">{{ $label }}</span>
        @endif
    </span>
</div>
