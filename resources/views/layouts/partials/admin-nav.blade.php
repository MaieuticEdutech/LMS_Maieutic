{{--
    Shared sidebar content — rendered once for the sticky desktop aside and
    once inside the mobile drawer (layouts/admin.blade.php) so the two never
    drift out of sync. Not a route-bearing view on its own.

    NAV ITEMS ARE ADDED ONE PHASE-4 CHECKPOINT AT A TIME, deliberately never
    all at once: a nav link to a route that doesn't exist yet is a broken
    link waiting to happen. Add the entry (and its icon below) in the same
    checkpoint that registers its route.

    $collapsible — true only for the desktop aside, which can collapse to an
    84px icon rail. The mobile drawer is already an overlay the user dismisses,
    so collapsing it would be a control with nothing to do; it passes false and
    renders no toggle. The state itself (`navCollapsed`) lives on the body in
    layouts/admin.blade.php, because the aside's width has to react to it.

    WHY LABELS GO TO sr-only AND NOT x-show WHEN COLLAPSED:
    x-show sets display:none, which takes the text out of the accessibility
    tree — every nav link in the rail would lose its accessible name and be
    announced as an unlabelled link. sr-only hides it from sight while leaving
    it readable to a screen reader, so the rail stays navigable.
--}}
@php
    // Organisation identity from BrandingService, never a literal (rule S-1).
    // Assigned inside this existing block rather than via a second @php
    // directive: a further raw php block in this file stops every directive
    // after it from compiling (the trap layouts/app.blade.php documents).
    $branding = app(\App\Services\Settings\BrandingService::class);

    $collapsible ??= false;

    // The domain screens — what an administrator works on day to day.
    $primaryItems = [
        ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
        ['route' => 'admin.students.index', 'label' => 'Students', 'icon' => 'students'],
        ['route' => 'admin.instructors.index', 'label' => 'Instructors', 'icon' => 'instructors'],
        ['route' => 'admin.courses.index', 'label' => 'Courses', 'icon' => 'courses'],
        ['route' => 'admin.categories.index', 'label' => 'Categories', 'icon' => 'categories'],
        // Phase 6 (Track C). Guarded by the Route::has() check below, as
        // every entry here is.
        ['route' => 'admin.enrollments.index', 'label' => 'Enrolments', 'icon' => 'enrollments'],
        // Phase 8.
        ['route' => 'admin.assessments.index', 'label' => 'Assessments', 'icon' => 'assessments'],
        ['route' => 'admin.reports.enrollments', 'label' => 'Reports', 'icon' => 'reports'],
    ];

    // Operational screens — these answer questions about the system rather
    // than about a course or a student, so they sit below a divider. Phase 11
    // added most of them.
    $operationalItems = [
        ['route' => 'admin.audit-log.index', 'label' => 'Audit log', 'icon' => 'audit'],
        ['route' => 'admin.email-log.index', 'label' => 'Email log', 'icon' => 'mail'],
        ['route' => 'admin.queue-health.index', 'label' => 'Queue health', 'icon' => 'queue'],
        ['route' => 'admin.settings.index', 'label' => 'Settings', 'icon' => 'settings'],
    ];

    // Inline SVGs (Lucide-shaped path data, hand-copied — no icon package;
    // package.json is Track C's, and docs/UI-GUIDE.md §15 leaves an icon
    // library as an open question. currentColor, 1.75 stroke, 16px.).
    $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect>',
        'students' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'instructors' => '<path d="M2 3h20"></path><path d="M21 3v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3"></path><path d="m7 21 5-5 5 5"></path>',
        'courses' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>',
        'categories' => '<path d="M3 7a2 2 0 0 1 2-2h3l2 2h9a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>',
        'enrollments' => '<rect x="8" y="2" width="8" height="4" rx="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><path d="M12 11h4"></path><path d="M12 16h4"></path><path d="M8 11h.01"></path><path d="M8 16h.01"></path>',
        'assessments' => '<circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><path d="M12 17h.01"></path>',
        'settings' => '<line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="2" y1="14" x2="6" y2="14"></line><line x1="10" y1="8" x2="14" y2="8"></line><line x1="18" y1="16" x2="22" y2="16"></line>',
        'audit' => '<path d="M9 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"></path><path d="M9 3v0a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2v0"></path><path d="M9 12h6"></path><path d="M9 16h6"></path>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>',
        'queue' => '<path d="M12 2v4"></path><path d="m16.2 7.8 2.9-2.9"></path><path d="M18 12h4"></path><path d="m16.2 16.2 2.9 2.9"></path><path d="M12 18v4"></path><path d="m4.9 19.1 2.9-2.9"></path><path d="M2 12h4"></path><path d="m4.9 4.9 2.9 2.9"></path>',
        'reports' => '<path d="M3 3v18h18"></path><path d="m7 14 3-4 3 3 5-6"></path>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><path d="m16 17 5-5-5-5"></path><path d="M21 12H9"></path>',
    ];

    $linkBase = 'group flex items-center gap-3 rounded-control px-3.5 py-2.5 text-sm transition-colors';
    $linkIdle = 'font-medium text-teal-200 hover:bg-white/8 hover:text-white';
    $linkActive = 'bg-teal-500 font-semibold text-white';
@endphp

{{-- Brand header. Centres to a single mark once the rail is collapsed. --}}
<div
    class="flex items-center gap-2.5 border-b border-white/12 px-4 py-4"
    @if ($collapsible) x-bind:class="navCollapsed && 'flex-col gap-3 px-2'" @endif
>
    @if ($branding->logoUrl())
        <img src="{{ $branding->logoUrl() }}" alt="" class="h-9 w-9 shrink-0 rounded-control object-contain">
    @else
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-control bg-teal-400 font-serif text-lg font-semibold text-teal-900">
            {{ \Illuminate\Support\Str::substr($branding->organisationName(), 0, 1) }}
        </div>
    @endif

    <div class="min-w-0 flex-1" @if ($collapsible) x-bind:class="navCollapsed && 'sr-only'" @endif>
        <div class="truncate font-serif text-xl font-semibold tracking-tight text-white">{{ $branding->organisationName() }}</div>
        <div class="eyebrow mt-0.5 text-white/55">Admin console</div>
    </div>

    @if ($collapsible)
        <button
            type="button"
            x-on:click="toggleNav()"
            x-bind:aria-label="navCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
            x-bind:aria-expanded="(! navCollapsed).toString()"
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-sm text-teal-200 transition-colors hover:bg-white/10 hover:text-white"
        >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" x-bind:class="navCollapsed && 'rotate-180'">
                <path d="m15 18-6-6 6-6"></path>
            </svg>
        </button>
    @endif
</div>

<nav class="flex flex-1 flex-col gap-0.5 overflow-y-auto px-3 py-3.5" aria-label="Admin navigation">
    @foreach ($primaryItems as $item)
        @continue(! Route::has($item['route']))
        @php $active = request()->routeIs($item['route'].'*'); @endphp
        <a href="{{ route($item['route']) }}"
           @if ($active) aria-current="page" @endif
           @if ($collapsible) x-bind:class="navCollapsed && 'justify-center'" @endif
           class="{{ $linkBase }} {{ $active ? $linkActive : $linkIdle }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 {{ $active ? '' : 'text-teal-300 group-hover:text-teal-100' }}">
                {!! $icons[$item['icon']] !!}
            </svg>
            <span @if ($collapsible) x-bind:class="navCollapsed && 'sr-only'" @endif>{{ $item['label'] }}</span>
        </a>
    @endforeach

    @php
        $hasOperational = collect($operationalItems)->contains(fn (array $item): bool => Route::has($item['route']));
    @endphp

    @if ($hasOperational)
        <div class="mx-3.5 my-2.5 h-px bg-white/12" role="presentation"></div>
    @endif

    @foreach ($operationalItems as $item)
        @continue(! Route::has($item['route']))
        @php $active = request()->routeIs($item['route'].'*'); @endphp
        <a href="{{ route($item['route']) }}"
           @if ($active) aria-current="page" @endif
           @if ($collapsible) x-bind:class="navCollapsed && 'justify-center'" @endif
           class="{{ $linkBase }} {{ $active ? $linkActive : $linkIdle }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 {{ $active ? '' : 'text-teal-300 group-hover:text-teal-100' }}">
                {!! $icons[$item['icon']] !!}
            </svg>
            <span @if ($collapsible) x-bind:class="navCollapsed && 'sr-only'" @endif>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>

@auth
    <div
        class="flex items-center gap-2.5 border-t border-white/12 px-4 py-3.5"
        @if ($collapsible) x-bind:class="navCollapsed && 'flex-col gap-3 px-2'" @endif
    >
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white text-xs font-bold text-teal-800">
            {{ Str::of(auth()->user()->name)->explode(' ')->map(fn ($part) => Str::substr($part, 0, 1))->take(2)->join('') }}
        </div>
        <div class="min-w-0 flex-1" @if ($collapsible) x-bind:class="navCollapsed && 'sr-only'" @endif>
            <div class="truncate text-sm font-semibold text-white">{{ auth()->user()->name }}</div>
            <div class="text-xs text-white/55">{{ auth()->user()->role->label() }}</div>
        </div>

        {{-- Logout stays a POST form: a GET link would let any cross-origin
             image tag sign an administrator out. --}}
        <form method="POST" action="{{ route('logout') }}" class="shrink-0">
            @csrf
            <button
                type="submit"
                aria-label="Log out"
                class="flex h-8 w-8 items-center justify-center rounded-sm text-teal-200 transition-colors hover:bg-white/10 hover:text-white"
            >
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    {!! $icons['logout'] !!}
                </svg>
            </button>
        </form>
    </div>
@endauth
