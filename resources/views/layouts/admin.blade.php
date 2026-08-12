{{--
    ADMINISTRATOR LAYOUT — super_admin only.

    Used by the Administrator Area (Phases 4-6, 8, 12, 13).

    Every route rendered inside this layout sits behind
    ['auth', 'active', 'role:super_admin'] AND an explicit policy check on the
    specific record. This layout being reachable proves nothing about
    authorisation — it is presentation only (Development Rule 20).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Administration') &middot; {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full bg-zinc-100 text-zinc-900 antialiased">
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-brand-600 focus:px-4 focus:py-2 focus:text-white">
        Skip to content
    </a>

    <div class="flex min-h-full">
        <aside class="hidden w-64 shrink-0 border-r border-zinc-200 bg-white lg:block">
            <div class="px-4 py-4">
                <span class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Administration</span>
            </div>
            <nav class="px-2 pb-4">
                {{-- Phase 4 adds: Dashboard, Courses, Students, Instructors,
                     Enrollments, Orders, Reports, Audit Log, Settings. --}}
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="border-b border-zinc-200 bg-white">
                <div class="flex items-center justify-between px-4 py-3 sm:px-6">
                    <div>
                        {{-- Phase 4 adds breadcrumbs. --}}
                        <h1 class="text-base font-semibold">@yield('heading', 'Administration')</h1>
                    </div>
                    {{-- Phase 4 adds the user menu. --}}
                </div>
            </header>

            {{-- Flash region. Every mutating action gives explicit feedback (NFR-UX-04). --}}
            @if (session('status'))
                <div class="px-4 pt-4 sm:px-6">
                    <x-alert variant="success">{{ session('status') }}</x-alert>
                </div>
            @endif

            <main id="main" class="flex-1 px-4 py-6 sm:px-6">
                @yield('content')
                {{ $slot ?? '' }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
