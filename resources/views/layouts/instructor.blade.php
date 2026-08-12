{{--
    INSTRUCTOR LAYOUT — instructor only.

    Used by the Instructor Area (Phase 10).

    SCOPE REMINDER (FR-INS-09, FR-INS-10, AC-03): everything rendered inside
    this layout concerns ASSIGNED courses only, and NO financial data — no
    order, payment or revenue figure may ever appear here. The navigation
    below must never gain a "Payments" or "Revenue" entry.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Instructor') &middot; {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex min-h-full flex-col bg-zinc-50 text-zinc-900 antialiased">
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-brand-600 focus:px-4 focus:py-2 focus:text-white">
        Skip to content
    </a>

    <header class="border-b border-zinc-200 bg-white">
        <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
            <span class="text-lg font-semibold">{{ config('app.name') }} <span class="text-zinc-400">Instructor</span></span>
            {{-- Phase 10 adds: Dashboard, My Courses, Students, Assessments, Results. --}}
        </nav>
    </header>

    <main id="main" class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8">
        @yield('content')
        {{ $slot ?? '' }}
    </main>

    @livewireScripts
</body>
</html>
