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
<body class="flex min-h-full flex-col bg-white text-neutral-900 antialiased">
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-teal-600 focus:px-4 focus:py-2 focus:text-white">
        Skip to content
    </a>

    <header class="border-b border-neutral-200">
        <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="text-lg font-semibold text-neutral-900">
                {{ config('app.name') }}
            </a>
            {{-- Phase 5 adds catalogue navigation; Phase 2 adds auth links. --}}
        </nav>
    </header>

    <main id="main" class="flex-1">
        @yield('content')
        {{ $slot ?? '' }}
    </main>

    <footer class="border-t border-neutral-200">
        <div class="mx-auto max-w-7xl px-4 py-6 text-sm text-neutral-500 sm:px-6 lg:px-8">
            &copy; {{ now()->year }} {{ config('app.name') }}
        </div>
    </footer>

    @livewireScripts
</body>
</html>
