@props([
    'variant' => 'info',
    'title' => null,
])

{{--
    Inline feedback banner.

    role="alert" on error/warning so assistive technology announces it
    immediately; role="status" for informational messages so it is announced
    politely without interrupting (WCAG 2.1 AA, NFR-UX-04).

    Soft tint with a hairline ring, matching the reference. Loud filled
    banners are not part of this language — the message carries the weight,
    not the colour.
--}}

@php
    $variants = [
        'info' => ['wrap' => 'bg-teal-50 text-teal-800 ring-teal-200', 'role' => 'status'],
        'success' => ['wrap' => 'bg-success-50 text-success-700 ring-success-600/25', 'role' => 'status'],
        'warning' => ['wrap' => 'bg-warning-50 text-warning-700 ring-warning-600/25', 'role' => 'alert'],
        'danger' => ['wrap' => 'bg-red-50 text-red-600 ring-red-100', 'role' => 'alert'],
    ];

    $config = $variants[$variant] ?? $variants['info'];
@endphp

<div role="{{ $config['role'] }}" {{ $attributes->class([
    'rounded-control px-4 py-3 text-sm ring-1 ring-inset',
    $config['wrap'],
]) }}>
    @if ($title)
        {{-- Sans, not serif: an alert title is UI, not editorial. --}}
        <p class="font-sans font-semibold">{{ $title }}</p>
    @endif
    <div @class(['mt-1' => $title])>{{ $slot }}</div>
</div>
