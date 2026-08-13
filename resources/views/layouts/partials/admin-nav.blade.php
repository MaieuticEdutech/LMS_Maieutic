{{--
    Admin navigation links.

    Included twice — once in the desktop rail, once in the mobile drawer — so
    the two can never drift apart. Duplicating the markup would guarantee that
    a nav item added in one place goes missing in the other, and the one that
    goes missing is always the mobile one nobody tests.

    A link to a route that does not exist yet is skipped rather than rendered
    broken, which is what lets nav entries land in the same checkpoint as their
    route.
--}}
@php
    $navItems = [
        ['route' => 'admin.dashboard', 'label' => 'Dashboard'],
        ['route' => 'admin.students.index', 'label' => 'Students'],
        ['route' => 'admin.instructors.index', 'label' => 'Instructors'],
        ['route' => 'admin.courses.index', 'label' => 'Courses'],
        ['route' => 'admin.settings.index', 'label' => 'Settings'],
        ['route' => 'admin.audit-log.index', 'label' => 'Audit Log'],
    ];
@endphp

<nav class="space-y-1 px-3 py-4" aria-label="Admin navigation">
    @foreach ($navItems as $item)
        @continue(! Route::has($item['route']))
        @php($active = request()->routeIs($item['route'].'*'))

        <a href="{{ route($item['route']) }}"
           @if ($active) aria-current="page" @endif
           class="block rounded-control px-3 py-2 text-sm font-medium transition-colors {{ $active ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
            {{ $item['label'] }}
        </a>
    @endforeach
</nav>
