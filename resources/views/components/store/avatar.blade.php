@props([
    'name' => '',
    // A PUBLIC photo URL, or null for the initials disc. Student profile photos
    // are private and encrypted, so they never come through here.
    'photo' => null,
    'size' => 32,
])

@php
    $initials = collect(preg_split('/\s+/', trim((string) $name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

@if ($photo)
    <img src="{{ $photo }}" alt="" loading="lazy"
         {{ $attributes->class('inline-block shrink-0 rounded-full object-cover ring-2 ring-white') }}
         style="width: {{ $size }}px; height: {{ $size }}px">
@else
    <span {{ $attributes->class('inline-grid shrink-0 place-items-center rounded-full bg-teal-600 font-semibold text-white ring-2 ring-white') }}
          style="width: {{ $size }}px; height: {{ $size }}px; font-size: {{ max(10, (int) round($size * 0.36)) }}px"
          aria-hidden="true">{{ $initials }}</span>
@endif
