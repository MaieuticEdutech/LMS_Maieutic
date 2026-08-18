{{--
    Shared sidebar content for the instructor shell — mirrors
    layouts/partials/admin-nav.blade.php exactly (docs/UI-GUIDE.md §7 groups
    admin and instructor under the same "dark sidebar" treatment). Rendered
    once for the sticky desktop aside and once inside the mobile drawer.
--}}
@php
    // Organisation identity from BrandingService, never a literal (rule S-1).
    // Assigned inside this existing block rather than via a second @php
    // directive: a further raw php block in this file stops every directive
    // after it from compiling, and the error points at an @endforeach nowhere
    // near the cause (the same trap layouts/app.blade.php documents).
    $branding = app(\App\Services\Settings\BrandingService::class);

    $navItems = [
        ['route' => 'instructor.home', 'label' => 'Dashboard', 'icon' => 'dashboard'],
        ['route' => 'instructor.courses.index', 'label' => 'My courses', 'icon' => 'courses'],
        ['route' => 'instructor.assessments.index', 'label' => 'Assessments', 'icon' => 'assessments'],
        ['route' => 'instructor.reports.enrollments', 'label' => 'Reports', 'icon' => 'reports'],
    ];

    // Same inline-SVG approach as the admin nav — no icon package.
    $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect>',
        'courses' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>',
        'assessments' => '<circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><path d="M12 17h.01"></path>',
        'reports' => '<path d="M3 3v18h18"></path><path d="m7 14 3-4 3 3 5-6"></path>',
    ];
@endphp

<div class="border-b border-white/12 px-5 py-5">
    <div class="font-serif text-2xl font-semibold tracking-tight text-white">{{ $branding->organisationName() }}</div>
    <div class="eyebrow mt-1.5 text-white/55">Instructor</div>
</div>

<nav class="flex flex-1 flex-col gap-0.5 overflow-y-auto px-3 py-3.5" aria-label="Instructor navigation">
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
