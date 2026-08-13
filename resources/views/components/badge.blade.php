@props([
    'variant' => 'neutral',
])

{{--
    Status pill. Used for course status, enrollment status, attempt status,
    order status and payment status.

    Colour alone must never be the only signal (WCAG 2.1 AA 1.4.1): the label
    text always states the status, so a colour-blind user loses nothing.

    Soft tint background with darker text of the same family — never a
    saturated fill (docs/UI-GUIDE.md §8). A pill should read as a quiet label,
    not compete with the primary action on the screen.
--}}

@php
    $variants = [
        'neutral' => 'bg-neutral-100 text-neutral-700 ring-neutral-300',
        'brand' => 'bg-teal-50 text-teal-700 ring-teal-200',
        'success' => 'bg-success-50 text-success-700 ring-success-600/25',
        'warning' => 'bg-warning-50 text-warning-700 ring-warning-600/25',
        'danger' => 'bg-red-50 text-red-600 ring-red-200',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset whitespace-nowrap',
    $variants[$variant] ?? $variants['neutral'],
]) }}>
    {{ $slot }}
</span>
