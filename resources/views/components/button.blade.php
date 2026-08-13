@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
])

{{--
    Button / link-button.

    NFR-UX-04: destructive actions use variant="danger" AND must additionally
    be confirmed by the caller (x-modal with typed confirmation for admin
    destructive actions — FR-ADM-17).

    Rendering a button never implies permission. The route behind it is
    authorised server-side regardless (Development Rule 20).

    STYLING (docs/UI-GUIDE.md §8): teal is the only call-to-action colour, so
    there is exactly one primary button per screen. Hover darkens one step,
    press darkens one further — never a colour flip, never a scale bounce.
--}}

@php
    $variants = [
        // The single CTA. One per screen.
        'primary' => 'bg-teal-600 text-white shadow-xs hover:bg-teal-700 active:bg-teal-800',

        // Hairline border, not a filled grey block — hierarchy comes from
        // borders in this system.
        'secondary' => 'bg-white text-neutral-800 ring-1 ring-inset ring-neutral-200 hover:bg-neutral-50 hover:ring-neutral-300 active:bg-neutral-100',

        'danger' => 'bg-red-600 text-white shadow-xs hover:bg-red-700 active:bg-red-800',

        'ghost' => 'text-neutral-700 hover:bg-neutral-100 active:bg-neutral-200',
    ];

    // Fixed heights rather than vertical padding: buttons sitting beside
    // inputs must align exactly, and padding-derived heights drift as soon as
    // font-size or line-height changes.
    $sizes = [
        'sm' => 'h-8 px-3 text-xs gap-1.5',
        'md' => 'h-10 px-4 text-sm gap-2',
        'lg' => 'h-11 px-5 text-sm gap-2',
    ];

    $classes = implode(' ', [
        'inline-flex items-center justify-center rounded-control font-medium',
        'transition-colors duration-150 ease-out',
        'disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-current',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        {{ $slot }}
    </button>
@endif
