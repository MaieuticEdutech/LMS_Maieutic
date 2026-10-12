@props([
    'course',
    'href' => null,
    // 'Bestseller' | 'New' | 'Highest rated' | null.
    'badge' => null,
    'free' => false,
    // Learners on the course, when the caller has counted them.
    'learners' => null,
    // Public photo URL of the first instructor, when known.
    'instructorPhoto' => null,
])

{{--
    The storefront course card (Spotlight × Canvas). Every figure on it is real:
    rating and count from courses.rating_*, hours and lessons from the cached
    counters, level from the column, learners from an enrolment count.

    The instructor line reads `instructors` only if the caller eager-loaded it.
--}}
@php
    $instructor = $course->relationLoaded('instructors') ? $course->instructors->first() : null;
    $hours = $course->total_duration_seconds > 0 ? round($course->total_duration_seconds / 3600, 1) : null;
    $badgeStyles = [
        'Bestseller' => 'bg-[#fef79a] text-[#3d3c0a]',
        'New' => 'bg-teal-50 text-teal-700',
        'Highest rated' => 'bg-marigold text-[#7a4510]',
    ];
@endphp

<article {{ $attributes->class('group relative flex flex-col overflow-hidden rounded-[20px] border border-neutral-200 bg-white transition duration-300 ease-standard hover:-translate-y-1 hover:shadow-lg') }}>
    <div class="relative overflow-hidden">
        <x-store.course-art :course="$course" class="transition-transform duration-500 ease-standard group-hover:scale-[1.04]" />

        @if ($badge)
            <span class="absolute left-3 top-3 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $badgeStyles[$badge] ?? $badgeStyles['New'] }}">{{ $badge }}</span>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-1.5 px-4 pb-4 pt-3.5">
        @if ($instructor)
            <span class="flex items-center gap-2 text-[12.5px] font-semibold text-neutral-700">
                <x-store.avatar :name="$instructor->name" :photo="$instructorPhoto" :size="24" />
                {{ $instructor->name }}
            </span>
        @endif

        <h3 class="font-sans text-[15.5px] font-bold leading-snug text-neutral-900">
            @if ($href)
                <a href="{{ $href }}" class="after:absolute after:inset-0 focus-visible:outline-none">{{ $course->title }}</a>
            @else
                {{ $course->title }}
            @endif
        </h3>

        <x-store.rating :course="$course" />

        <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-neutral-500">
            @if ($hours)
                <span>{{ rtrim(rtrim(number_format($hours, 1), '0'), '.') }}h</span>
            @endif
            @if ($course->lessons_count > 0)
                <span>{{ $course->lessons_count }} {{ Str::plural('lesson', $course->lessons_count) }}</span>
            @endif
            @if ($course->level)
                <span>{{ $course->level->label() }}</span>
            @endif
            @if ($learners)
                <span>{{ number_format($learners) }} {{ Str::plural('learner', $learners) }}</span>
            @endif
        </div>

        <div class="mt-auto pt-2">
            <x-store.price :course="$course" :free="$free" size="sm" />
        </div>
    </div>
</article>
