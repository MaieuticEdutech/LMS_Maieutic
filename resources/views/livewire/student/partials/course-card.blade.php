{{--
    One enrolled course, as the mockup draws it: a 16/9 two-tone tile above the
    title, with the progress bar pinned to the bottom of the card.

    Shared by the dashboard grid and My Learning so the two cannot drift — the
    same reason the admin nav lives in a partial.

    The WHOLE CARD is the link rather than a "Continue" button in the corner. A
    card that looks clickable but only responds in one small area is a reliable
    way to make an interface feel broken. The heading carries the accessible
    name; the surrounding anchor is the hit area.

    ═════════════════════════════════════════════════════════════════════════
    WHERE THE MOCKUP SHOWS AN INSTRUCTOR NAME, THIS SHOWS LESSONS AND LEVEL.

    Not a design change for its own sake. `Model::preventLazyLoading()` is
    active outside production, and the queries feeding this card eager-load
    `course.category` and nothing else — touching `$course->instructor` here
    would throw on the dashboard and fire one query per card if it did not.
    Lessons count and level are columns already on the row, so they cost
    nothing and fill the same line honestly.
    ═════════════════════════════════════════════════════════════════════════

    Expects: $enrollment (with `course` and `course.category` loaded).
--}}
@php
    $course = $enrollment->course;
    $percent = (int) $enrollment->progress_percentage;
    $finished = $enrollment->completed_at !== null;
@endphp

<a href="{{ route('student.courses.play', $course) }}"
   class="group flex flex-col overflow-hidden rounded-card border border-neutral-200 bg-white transition-all duration-[180ms] ease-standard hover:-translate-y-px hover:border-neutral-300 hover:shadow-[0_2px_8px_rgba(26,24,21,0.06)]">

    @include('partials.course-thumb', [
        'course' => $course,
        'badge' => $finished
            ? '<span class="rounded-full bg-honeydew px-[10px] py-1 text-[10.5px] font-semibold text-white">Completed</span>'
            : null,
    ])

    <div class="flex flex-1 flex-col gap-2 px-5 pb-5 pt-[18px]">
        <h3 class="font-sans text-base/[1.35] font-semibold tracking-normal text-neutral-900 group-hover:text-teal-700">
            {{ $course->title }}
        </h3>

        <p class="text-[13px] text-neutral-500">
            {{ $course->lessons_count }} {{ Str::plural('lesson', $course->lessons_count) }} · {{ $course->level->label() }}
        </p>

        {{-- mt-auto pins the progress row to the bottom, so cards of differing
             title lengths still line up across the grid. --}}
        <div class="mt-auto flex items-center gap-[10px] pt-[10px]">
            {{-- x-progress-bar, not a hand-rolled div: at 6px tall its
                 `rounded-full` IS the mockup's 3px radius, and its groove and
                 fill are the same two tokens. Forking it to save one prop is
                 how a design system starts disagreeing with itself. The
                 percentage is written beside it, so it stays decorative. --}}
            <x-progress-bar :value="$percent" :tone="$finished ? 'success' : 'brand'" class="flex-1" />

            <span class="font-mono text-xs font-semibold text-neutral-700">{{ $percent }}%</span>
        </div>
    </div>
</a>
