{{--
    One enrolled course.

    Shared by the dashboard grid and My Courses so the two cannot drift — the
    same reason the admin nav lives in a partial.

    The WHOLE CARD is the link rather than a "Continue" button in the corner.
    A card that looks clickable but only responds in one small area is a
    reliable way to make an interface feel broken. The heading carries the
    accessible name; the surrounding anchor is the hit area.

    Expects: $enrollment (with `course` loaded).
--}}
@php
    $course = $enrollment->course;
    $percent = (int) $enrollment->progress_percentage;
@endphp

<a href="{{ route('student.courses.play', $course) }}"
   class="group flex flex-col rounded-card border border-neutral-200 bg-white p-5 transition-all hover:-translate-y-px hover:border-neutral-300 hover:shadow-sm">

    @if ($course->category)
        <p class="eyebrow text-teal-600">{{ $course->category->name }}</p>
    @endif

    <h3 class="mt-2 text-lg text-balance group-hover:text-teal-700">{{ $course->title }}</h3>

    @if ($course->subtitle)
        <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-neutral-500">{{ $course->subtitle }}</p>
    @endif

    {{-- mt-auto pins the progress row to the bottom, so cards of differing
         title lengths still line up across the grid. --}}
    <div class="mt-auto pt-5">
        <div class="flex items-center justify-between font-mono text-[11px] tracking-[0.04em] text-neutral-500">
            <span>{{ $percent }}% COMPLETE</span>
            <span>{{ $course->lessons_count }} {{ Str::plural('LESSON', $course->lessons_count) }}</span>
        </div>

        {{-- aria-hidden: the percentage above already states this in text, and
             a screen reader announcing a decorative bar twice is noise. --}}
        <div class="mt-2 h-1 overflow-hidden rounded-full bg-neutral-100" aria-hidden="true">
            <div class="h-full rounded-full bg-teal-600 transition-[width]" style="width: {{ max(2, $percent) }}%"></div>
        </div>
    </div>
</a>
