@props([
    'variant' => 'info',
    'title' => null,
])

{{--
    Inline feedback banner.

    role="alert" on error/warning so assistive technology announces it
    immediately; role="status" for informational messages so it is announced
    politely without interrupting (WCAG 2.1 AA, NFR-UX-04).
--}}

@php
    $variants = [
        'info' => ['wrap' => 'bg-brand-50 text-brand-900 ring-brand-200', 'role' => 'status'],
        'success' => ['wrap' => 'bg-success-50 text-success-700 ring-success-600/20', 'role' => 'status'],
        'warning' => ['wrap' => 'bg-warning-50 text-warning-700 ring-warning-600/20', 'role' => 'alert'],
        'danger' => ['wrap' => 'bg-danger-50 text-danger-700 ring-danger-600/20', 'role' => 'alert'],
    ];

    $config = $variants[$variant] ?? $variants['info'];
@endphp

<div role="{{ $config['role'] }}" {{ $attributes->class(['rounded-control px-4 py-3 text-sm ring-1 ring-inset', $config['wrap']]) }}>
    @if ($title)
        <p class="font-semibold">{{ $title }}</p>
    @endif
    <div @class(['mt-0.5' => $title])>{{ $slot }}</div>
</div>
