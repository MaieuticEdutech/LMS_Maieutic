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
@endphp

<div
    class="{{ $variant === 'side' ? 'w-[200px] flex-none' : 'aspect-video' }} flex items-end justify-between p-[14px]"
    style="background:linear-gradient(118deg,{{ $deep }} 0%,{{ $deep }} 62%,{{ $light }} 62%)"
    aria-hidden="true"
>
    {{-- Hidden from screen readers: the category is already in the card's text
         below, and hearing it twice is noise. --}}
    <span class="font-mono text-[10px] font-semibold tracking-[0.12em] text-white/85">
        {{ Str::upper($course->category?->name ?? 'COURSE') }}
    </span>

    @if ($badge)
        {!! $badge !!}
    @endif
</div>
