@props([
    'course',
    // Any CSS aspect-ratio, e.g. '16/9' (cards) or '3/4' (Top 10 posters).
    'ratio' => '16/9',
    'label' => true,
])

{{--
    A course's cover. An uploaded thumbnail wins; otherwise the illustrated
    scene from App\Support\CourseArt (an editor for development, a chart for
    data, a shield for security, ...).

    Reads the thumbnail ONLY from an already-loaded relation, like
    partials/course-thumb: preventLazyLoading is on outside production, and a
    grid of cards must not fire a query per card. A caller that hasn't eager
    loaded `thumbnail` gets the illustration.
--}}
@php
    $thumbnail = $course->relationLoaded('thumbnail') ? $course->thumbnail->first() : null;
    $thumbnailUrl = $thumbnail !== null ? Storage::disk($thumbnail->disk)->url($thumbnail->path) : null;
@endphp

<div {{ $attributes->class(['relative overflow-hidden', $thumbnailUrl ? 'bg-neutral-100' : 'bg-teal-900']) }} style="aspect-ratio: {{ $ratio }}" aria-hidden="true">
    {{-- The illustration is the FALLBACK, never a backdrop. Drawn behind an
         uploaded image it shows through anything transparent (a logo PNG),
         which is how a thumbnail ended up with a code editor behind it. --}}
    @if ($thumbnailUrl)
        <img src="{{ $thumbnailUrl }}" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
    @else
        {!! \App\Support\CourseArt::svg($course) !!}
    @endif

    @if ($label && ! $thumbnailUrl)
        <span class="absolute bottom-2.5 left-3 font-mono text-[10px] font-semibold uppercase tracking-[0.14em] text-white/85">
            {{ $course->category?->name ?? 'Course' }}
        </span>
    @endif
</div>
