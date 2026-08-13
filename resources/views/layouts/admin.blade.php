{{--
    ADMINISTRATOR LAYOUT — super_admin only.

    Used by the Administrator Area (Phases 4-6, 8, 12, 13).

    Every route rendered inside this layout sits behind
    ['auth', 'active', 'role:super_admin'] AND an explicit policy check on the
    specific record. This layout being reachable proves nothing about
    authorisation — it is presentation only (Development Rule 20).

    $breadcrumbs — optional list of ['label' => string, 'url' => string|null],
    rendered via <x-breadcrumbs>. Pages that don't pass one simply show none.

    NAV ITEMS ARE ADDED ONE PHASE-4 CHECKPOINT AT A TIME, deliberately never
    all at once: a nav link to a route that doesn't exist yet is a broken
    link waiting to happen. Add the entry in the same checkpoint that
    registers its route.
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
            <nav class="space-y-1 px-2 pb-4" aria-label="Admin navigation">
                @php
                    $navItems = [
                        ['route' => 'admin.dashboard', 'label' => 'Dashboard'],
                        ['route' => 'admin.students.index', 'label' => 'Students'],
                    ];
                @endphp

                @foreach ($navItems as $item)
                    @continue(! Route::has($item['route']))
                    @php $active = request()->routeIs($item['route'].'*'); @endphp
                    <a href="{{ route($item['route']) }}"
                       @if ($active) aria-current="page" @endif
                       class="block rounded-control px-3 py-2 text-sm font-medium {{ $active ? 'bg-brand-50 text-brand-700' : 'text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="border-b border-zinc-200 bg-white">
                <div class="flex items-center justify-between px-4 py-3 sm:px-6">
                    <div>
                        <x-breadcrumbs :items="$breadcrumbs ?? []" />
                        <h1 class="text-base font-semibold">@yield('heading', 'Administration')</h1>
                    </div>

                    @auth
                        <div class="flex items-center gap-3">
                            <div class="text-right text-sm">
                                <p class="font-medium leading-tight">{{ auth()->user()->name }}</p>
                                <p class="leading-tight text-zinc-500">{{ auth()->user()->role->label() }}</p>
                            </div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-button type="submit" variant="secondary" size="sm">Log out</x-button>
                            </form>
                        </div>
                    @endauth
                </div>
            </header>

            {{-- Flash region. Every mutating action gives explicit feedback (NFR-UX-04). --}}
            @if (session('status'))
                <div class="px-4 pt-4 sm:px-6">
                    <x-alert variant="success">{{ session('status') }}</x-alert>
                </div>
            @endif

            @if (session('error'))
                <div class="px-4 pt-4 sm:px-6">
                    <x-alert variant="danger">{{ session('error') }}</x-alert>
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
