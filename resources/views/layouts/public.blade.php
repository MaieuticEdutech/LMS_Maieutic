{{--
    PUBLIC LAYOUT — guests and unauthenticated visitors.

    Used by the marketing surface, the course catalogue and course detail
    pages (Phase 5).

    ACCESS BOUNDARY (AC-01): pages using this layout render course METADATA
    ONLY. No lesson body, media file, resource or assessment may be rendered
    or linked from here. There is no preview exemption in V1 (ADR-014).

    Organisation name comes from config for now; Phase 2 replaces this with
    BrandingService reading the `settings` table, so that nothing hardcodes
    organisation identity (rule S-1, FR-SYS-01).

    Brand pass (docs/UI-GUIDE.md §7 "Public — editorial"): max-w-content
    (1240px) rhythm, 24px gutters, generous air. No sidebar — this is the
    most brand-visible surface in the product, so restraint matters more
    here than anywhere else in the app.
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
<body class="flex min-h-full flex-col bg-neutral-50 text-neutral-800 antialiased">
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-sm focus:bg-teal-600 focus:px-4 focus:py-2 focus:text-white">
        Skip to content
    </a>

    {{--
        A SIGNED-IN STUDENT KEEPS THEIR OWN HEADER HERE.

        The catalogue is a public page, but it is also the "Explore" tab of the
        student shell. Swapping a student back to the guest header the moment
        they browse would drop their navigation and their profile disc mid-session
        — it would read as having been signed out.

        Guests, instructors and administrators get the public header below:
        none of them has a My Learning list, so the student nav would be three
        links to somewhere they cannot go.
    --}}
    @if (auth()->user()?->isStudent())
        @include('partials.student-header')
    @else
    <header class="border-b border-neutral-200 bg-neutral-0">
        <nav class="mx-auto flex max-w-content items-center justify-between gap-6 px-6 py-4" aria-label="Primary">
            <a href="{{ route('home') }}" class="shrink-0">
                <img src="{{ asset('images/logo-maieutic.png') }}" alt="Maieutic" class="h-8 w-auto">
            </a>

            <div class="flex items-center gap-6">
                @if (Route::has('catalogue.index'))
                    <a href="{{ route('catalogue.index') }}" class="text-sm font-medium text-neutral-700 hover:text-teal-700">
                        Catalogue
                    </a>
                @endif

                @auth
                    <x-button :href="auth()->user()->role->homePath()" variant="secondary" size="sm" wire:navigate>
                        Dashboard
                    </x-button>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="text-sm font-medium text-neutral-700 hover:text-teal-700">
                            Sign in
                        </a>
                    @endif
                @endauth
            </div>
        </nav>
    </header>
    @endif

    <main id="main" class="flex-1">
        @yield('content')
        {{ $slot ?? '' }}
    </main>

    <footer class="border-t border-neutral-200">
        <div class="mx-auto max-w-content px-6 py-8 text-sm text-neutral-500">
            &copy; {{ now()->year }} {{ config('app.name') }}
        </div>
    </footer>

    @livewireScripts
</body>
</html>
