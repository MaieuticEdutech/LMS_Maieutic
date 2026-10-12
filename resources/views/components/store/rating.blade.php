@props([
    'course',
    'tone' => 'default',
    'stars' => true,
])

{{--
    Stars, the mean and the review count. Renders nothing when nobody has rated
    the course, for the reason in partials/course-rating.blade.php: "no ratings
    yet" is not the same fact as "rated zero".
--}}
@if ($course->hasRatings())
    @php
        $mean = (float) $course->averageRating();
        $filled = (int) round($mean);
        $star = $tone === 'inverse' ? 'text-melon' : 'text-red-500';
    @endphp
    <span {{ $attributes->class('inline-flex items-center gap-1.5 text-[12.5px]') }}>
        <b class="font-bold {{ $star }}" aria-hidden="true">{{ number_format($mean, 1) }}</b>
        @if ($stars)
            <span class="inline-flex gap-px {{ $star }}" aria-hidden="true">
                @for ($i = 1; $i <= 5; $i++)
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="{{ $i <= $filled ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.6"><path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>
                @endfor
            </span>
        @endif
        <span class="{{ $tone === 'inverse' ? 'text-white/60' : 'text-neutral-500' }}" aria-hidden="true">({{ number_format($course->rating_count) }})</span>
        <span class="sr-only">Rated {{ number_format($mean, 1) }} out of 5 from {{ $course->rating_count }} {{ Str::plural('review', $course->rating_count) }}</span>
    </span>
@endif
