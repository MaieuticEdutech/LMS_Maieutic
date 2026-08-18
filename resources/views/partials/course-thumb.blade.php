{{--
    The two-tone diagonal a course card leads with.

    The mockup gives every course a `linear-gradient(118deg, A 0 62%, B 62%)`
    tile carrying its category in mono caps. That is the design's main unit of
    colour, so it is reproduced rather than replaced with a grey box.

    ═════════════════════════════════════════════════════════════════════════
    THE COLOUR PAIR IS DERIVED FROM THE COURSE ID, NOT PICKED AT RANDOM.

    Random would give the same course a different face on every page load,
    which reads as a rendering bug — a learner recognises their course by its
    colour before they read the title. Keying on the id makes it stable for the
    life of the course and spreads the palette evenly across a grid without
    anybody having to choose.

    Every pair is drawn from the brand tokens. `outcomes`, `level` and category
    are real columns; there is no invented data here.
    ═════════════════════════════════════════════════════════════════════════

    Expects: $course. Optional: $variant ('tile' default, 'side' for the wide
    dashboard card), $badge (extra markup pinned to the right).
--}}
@php
    $variant ??= 'tile';
    $badge ??= null;

    /*
     * Eight pairs, matching the mockup's own set: a deep brand tone against a
     * warm or coral light tone. Ordered so adjacent ids never repeat a pair.
     */
    $palettes = [
        ['#00615c', '#fef1de'],
        ['#024e4a', '#f2aa84'],
        ['#800d07', '#fef1de'],
        ['#052826', '#f8847e'],
        ['#043b38', '#f2aa84'],
        ['#167871', '#fef1de'],
        ['#a31009', '#fef1de'],
        ['#4a473f', '#f8847e'],
    ];

    [$deep, $light] = $palettes[$course->getKey() % count($palettes)];

    /*
     * A REAL UPLOADED THUMBNAIL WINS; THE GRADIENT IS THE FALLBACK.
     *
     * Thumbnails are the one PUBLIC medium in the system (FR-STU-04) — stored
     * on the `public` disk rather than the private content disk, precisely so
     * they can be rendered with a plain URL to a guest browsing the catalogue.
     * Until now nothing ever rendered them: an admin uploaded a thumbnail, it
     * was stored correctly, and every card still drew the gradient.
     *
     * Read ONLY from an already-loaded relation. Model::preventLazyLoading()
     * is active outside production, so touching $course->thumbnail from a card
     * inside a grid would throw — and in production it would quietly fire one
     * query per card. A caller that has not eager-loaded `thumbnail` gets the
     * gradient, exactly as before.
     */
    $thumbnail = $course->relationLoaded('thumbnail') ? $course->thumbnail->first() : null;

    $thumbnailUrl = $thumbnail !== null
        ? Storage::disk($thumbnail->disk)->url($thumbnail->path)
        : null;
@endphp

<div
    class="relative {{ $variant === 'side' ? 'w-[200px] flex-none' : 'aspect-video' }} flex items-end justify-between overflow-hidden p-[14px]"
    style="background:linear-gradient(118deg,{{ $deep }} 0%,{{ $deep }} 62%,{{ $light }} 62%)"
    aria-hidden="true"
>

    @if ($thumbnailUrl)
        {{-- The gradient stays underneath as the loading and broken-image
             state, so a card never collapses to a blank rectangle. --}}
        <img src="{{ $thumbnailUrl }}" alt="" loading="lazy"
             class="absolute inset-0 h-full w-full object-cover">
    @endif
    {{-- Hidden from screen readers: the category is already in the card's text
         below, and hearing it twice is noise. --}}
    <span class="font-mono text-[10px] font-semibold tracking-[0.12em] text-white/85">
        {{ Str::upper($course->category?->name ?? 'COURSE') }}
    </span>

    @if ($badge)
        {!! $badge !!}
    @endif
</div>
