{{--
    A course's mean rating (design handoff §2 — "★ 4.8" in #a31009).

    ═════════════════════════════════════════════════════════════════════════
    RENDERS NOTHING AT ALL WHEN NOBODY HAS RATED THE COURSE.

    Course::averageRating() returns null rather than 0.0 for exactly this
    reason: "no ratings yet" and "rated zero" are different facts, and a card
    showing ★ 0.0 on a brand-new course asserts the second when the first is
    true. An absent rating is honest; a zero is a bad review the course never
    received.
    ═════════════════════════════════════════════════════════════════════════

    The star is decorative — the accessible text says "4.8 out of 5", because a
    screen reader announcing "black star four point eight" is not a rating.

    Expects: $course. Optional: $tone ('default' | 'inverse' for dark panels).
--}}
@php($tone ??= 'default')

@if ($course->hasRatings())
    <span class="inline-flex items-center gap-1.5">
        <span class="font-semibold {{ $tone === 'inverse' ? 'text-melon' : 'text-red-500' }}" aria-hidden="true">
            ★ {{ number_format((float) $course->averageRating(), 1) }}
        </span>

        <span class="{{ $tone === 'inverse' ? 'text-white/60' : 'text-neutral-500' }}">
            ({{ number_format($course->rating_count) }})
        </span>

        <span class="sr-only">
            Rated {{ number_format((float) $course->averageRating(), 1) }} out of 5
            from {{ $course->rating_count }} {{ Str::plural('review', $course->rating_count) }}
        </span>
    </span>
@endif
