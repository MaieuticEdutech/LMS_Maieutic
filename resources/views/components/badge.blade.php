@props([
    'variant' => 'neutral',
])

{{--
    Status pill. Used for course status, enrollment status, attempt status,
    order status and payment status.

    Colour alone must never be the only signal (WCAG 2.1 AA 1.4.1): the label
    text always states the status, so a colour-blind user loses nothing.
--}}

@php
    $variants = [
        'neutral' => 'bg-zinc-100 text-zinc-700 ring-zinc-500/20',
        'brand' => 'bg-brand-50 text-brand-700 ring-brand-600/20',
        'success' => 'bg-success-50 text-success-700 ring-success-600/20',
        'warning' => 'bg-warning-50 text-warning-700 ring-warning-600/20',
        'danger' => 'bg-danger-50 text-danger-700 ring-danger-600/20',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
    $variants[$variant] ?? $variants['neutral'],
]) }}>
    {{ $slot }}
</span>
