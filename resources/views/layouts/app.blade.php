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
<body class="flex min-h-full flex-col bg-zinc-50 text-zinc-900 antialiased">
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-brand-600 focus:px-4 focus:py-2 focus:text-white">
        Skip to content
    </a>

    <header class="border-b border-zinc-200 bg-white">
        <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ url('/') }}" class="text-lg font-semibold">{{ config('app.name') }}</a>
            {{-- Phase 7 adds: Dashboard, My Courses, Profile, Logout. --}}
        </nav>
    </header>

    <main id="main" class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8">
        @yield('content')
        {{ $slot ?? '' }}
    </main>

    @livewireScripts
</body>
</html>
