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
--}}

@php
    $variants = [
        'primary' => 'bg-brand-600 text-white hover:bg-brand-700 focus-visible:outline-brand-600',
        'secondary' => 'bg-white text-zinc-700 ring-1 ring-inset ring-zinc-300 hover:bg-zinc-50',
        'danger' => 'bg-danger-600 text-white hover:bg-danger-700',
        'ghost' => 'text-zinc-700 hover:bg-zinc-100',
    ];

    $sizes = [
        'sm' => 'px-2.5 py-1.5 text-xs gap-1',
        'md' => 'px-3 py-2 text-sm gap-1.5',
        'lg' => 'px-4 py-2.5 text-sm gap-2',
    ];

    $classes = implode(' ', [
        'inline-flex items-center justify-center rounded-control font-medium',
        'transition-colors disabled:cursor-not-allowed disabled:opacity-50',
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
