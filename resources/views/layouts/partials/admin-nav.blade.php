{{--
    Shared sidebar content — rendered once for the sticky desktop aside and
    once inside the mobile drawer (layouts/admin.blade.php) so the two never
    drift out of sync. Not a route-bearing view on its own.

    NAV ITEMS ARE ADDED ONE PHASE-4 CHECKPOINT AT A TIME, deliberately never
    all at once: a nav link to a route that doesn't exist yet is a broken
    link waiting to happen. Add the entry (and its icon below) in the same
    checkpoint that registers its route.
--}}
@php
    $navItems = [
        ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
        ['route' => 'admin.students.index', 'label' => 'Students', 'icon' => 'students'],
        ['route' => 'admin.instructors.index', 'label' => 'Instructors', 'icon' => 'instructors'],
        ['route' => 'admin.courses.index', 'label' => 'Courses', 'icon' => 'courses'],
        ['route' => 'admin.settings.index', 'label' => 'Settings', 'icon' => 'settings'],
        ['route' => 'admin.audit-log.index', 'label' => 'Audit log', 'icon' => 'audit'],
    ];

    // Inline SVGs (Lucide-shaped path data, hand-copied — no icon package;
    // package.json is Track C's, and docs/UI-GUIDE.md §15 leaves an icon
    // library as an open question. currentColor, 1.75 stroke, 16px.).
    $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect>',
        'students' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'instructors' => '<path d="M2 3h20"></path><path d="M21 3v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3"></path><path d="m7 21 5-5 5 5"></path>',
        'courses' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>',
        'settings' => '<line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="2" y1="14" x2="6" y2="14"></line><line x1="10" y1="8" x2="14" y2="8"></line><line x1="18" y1="16" x2="22" y2="16"></line>',
        'audit' => '<path d="M9 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"></path><path d="M9 3v0a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2v0"></path><path d="M9 12h6"></path><path d="M9 16h6"></path>',
    ];
@endphp

<div class="border-b border-white/12 px-5 py-5">
    <div class="font-serif text-2xl font-semibold tracking-tight text-white">Maieutic</div>
    <div class="eyebrow mt-1.5 text-white/55">Admin console</div>
</div>

<nav class="flex flex-1 flex-col gap-0.5 overflow-y-auto px-3 py-3.5" aria-label="Admin navigation">
    @foreach ($navItems as $item)
        @continue(! Route::has($item['route']))
        @php $active = request()->routeIs($item['route'].'*'); @endphp
        <a href="{{ route($item['route']) }}"
           @if ($active) aria-current="page" @endif
           class="flex items-center gap-2.5 rounded-sm px-3 py-2.5 text-sm font-medium text-white/92 transition-colors {{ $active ? 'bg-white/10' : 'hover:bg-white/6' }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0">
                {!! $icons[$item['icon']] !!}
            </svg>
            {{ $item['label'] }}
        </a>
    @endforeach
</nav>

@auth
    <div class="flex items-center gap-2.5 border-t border-white/12 px-4 py-3.5">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white text-xs font-bold text-teal-800">
            {{ Str::of(auth()->user()->name)->explode(' ')->map(fn ($part) => Str::substr($part, 0, 1))->take(2)->join('') }}
        </div>
        <div class="min-w-0">
            <div class="truncate text-sm font-semibold text-white">{{ auth()->user()->name }}</div>
            <div class="text-xs text-white/55">{{ auth()->user()->role->label() }}</div>
        </div>
    </div>
@endauth
