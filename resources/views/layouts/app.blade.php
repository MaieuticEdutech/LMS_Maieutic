{{--
    STUDENT LAYOUT — authenticated students.

    Used by the dashboard, My Courses, the course player and profile
    (Phases 7-9, 12).

    NFR-UX-02: the player must be fully usable on a mobile device, so this
    layout is mobile-first and the navigation collapses rather than
    horizontally scrolling.

    Note: rendering a link here is presentation only. Every route behind it is
    independently authorised server-side — hiding a link is never the control
    (Development Rule 20, FR-RBAC-02).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex min-h-full flex-col bg-neutral-50 text-neutral-900 antialiased">
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-teal-600 focus:px-4 focus:py-2 focus:text-white">
        Skip to content
    </a>

    @php($branding = app(\App\Services\Settings\BrandingService::class))

    <header class="border-b border-neutral-200 bg-white">
        <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8"
             aria-label="Student navigation">
            <a href="{{ route('student.home') }}" class="shrink-0">
                <img src="{{ asset('images/logo-maieutic.png') }}"
                     alt="{{ $branding->organisationName() }}"
                     class="h-8 w-auto">
            </a>

            {{-- Signed-in navigation.

                 Three links written out rather than looped over an array. A
                 loop would need the array declared in a php block, and a
                 raw php block in this file stopped every directive after it
                 from compiling — the resulting error points at an endif
                 nowhere near the cause. Three items do not need a loop, and
                 the literal version is both shorter and impossible to get
                 wrong. --}}
            @if (auth()->check())
                <div class="flex items-center gap-1 sm:gap-2">
                    <a href="{{ route('student.home') }}"
                       @if (request()->routeIs('student.home')) aria-current="page" @endif
                       class="rounded-control px-3 py-2 text-sm font-medium transition-colors {{ request()->routeIs('student.home') ? 'bg-teal-50 text-teal-700' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900' }}">
                        Dashboard
                    </a>

                    <a href="{{ route('student.courses.index') }}"
                       @if (request()->routeIs('student.courses.*')) aria-current="page" @endif
                       class="rounded-control px-3 py-2 text-sm font-medium transition-colors {{ request()->routeIs('student.courses.*') ? 'bg-teal-50 text-teal-700' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900' }}">
                        My courses
                    </a>

                    <a href="{{ route('profile.show') }}"
                       @if (request()->routeIs('profile.*')) aria-current="page" @endif
                       class="rounded-control px-3 py-2 text-sm font-medium transition-colors {{ request()->routeIs('profile.*') ? 'bg-teal-50 text-teal-700' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900' }}">
                        Profile
                    </a>

                    <form method="POST" action="{{ route('logout') }}" class="ml-1">
                        @csrf
                        <x-button type="submit" variant="ghost" size="sm">Log out</x-button>
                    </form>
                </div>
            @endif
        </nav>
    </header>

    <main id="main" class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8">
        @yield('content')
        {{ $slot ?? '' }}
    </main>

    @livewireScripts
</body>
</html>
