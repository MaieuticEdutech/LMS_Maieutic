{{--
    Navigation between the four reports.

    ═════════════════════════════════════════════════════════════════════════
    WITHOUT THIS, THREE OF THE FOUR REPORTS WERE UNREACHABLE.

    All eight routes registered and every report worked, but the sidebar
    carried a single "Reports" link pointing at enrolments and nothing linked
    the screens to each other — so the only report anyone could open, or
    export, was the first one. A route that exists but cannot be clicked is
    not a delivered feature.
    ═════════════════════════════════════════════════════════════════════════

    THE DATE RANGE TRAVELS WITH THE TAB. Both dates are URL-bound, so carrying
    the current query string means switching from "enrolments in March" to
    "course progress" keeps March rather than silently resetting to all time —
    which would quietly answer a different question than the one being asked.

    One partial for both audiences; the prefix comes from the current route, so
    an instructor's tabs stay inside the instructor area.
--}}
@php
    $prefix = request()->routeIs('instructor.*') ? 'instructor' : 'admin';

    $tabs = [
        ['route' => $prefix.'.reports.enrollments', 'label' => 'Enrolments'],
        ['route' => $prefix.'.reports.course-progress', 'label' => 'Course progress'],
        ['route' => $prefix.'.reports.assessments', 'label' => 'Assessments'],
        ['route' => $prefix.'.reports.students', 'label' => 'Students'],
    ];

    // Preserved so the period survives the jump between reports.
    $carried = array_filter(request()->only(['from', 'to']), static fn ($v) => $v !== null && $v !== '');
@endphp

<nav class="mb-6 flex gap-1 overflow-x-auto border-b border-neutral-200" aria-label="Reports">
    @foreach ($tabs as $tab)
        @continue(! Route::has($tab['route']))

        @php($active = request()->routeIs($tab['route']))

        <a
            href="{{ route($tab['route'], $carried) }}"
            wire:navigate
            @if ($active) aria-current="page" @endif
            class="-mb-px whitespace-nowrap border-b-2 px-3.5 py-2.5 text-sm font-medium transition-colors
                {{ $active
                    ? 'border-teal-600 text-teal-700'
                    : 'border-transparent text-neutral-600 hover:border-neutral-300 hover:text-neutral-900' }}"
        >
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
