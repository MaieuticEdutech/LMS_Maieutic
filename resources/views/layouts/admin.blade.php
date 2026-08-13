{{--
    ADMINISTRATOR LAYOUT — super_admin only.

    Used by the Administrator Area (Phases 4-6, 8, 12, 13).

    Every route rendered inside this layout sits behind
    ['auth', 'active', 'role:super_admin'] AND an explicit policy check on the
    specific record. This layout being reachable proves nothing about
    authorisation — it is presentation only (Development Rule 20).

    $breadcrumbs — optional list of ['label' => string, 'url' => string|null],
    rendered via <x-breadcrumbs>. Pages that don't pass one simply show none.

    Brand pass (docs/UI-GUIDE.md §7 "Admin — dark sidebar", §16 Step 3):
    248px sticky teal-900 sidebar on large screens, collapsing to an
    off-canvas drawer below 1024px. Nav content lives in
    layouts/partials/admin-nav.blade.php so the desktop aside and the mobile
    drawer render identically from one source.
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
<body class="min-h-full bg-neutral-50 text-neutral-800 antialiased" x-data="{ drawerOpen: false }">
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-sm focus:bg-teal-600 focus:px-4 focus:py-2 focus:text-white">
        Skip to content
    </a>

    <div class="flex min-h-full">
        {{-- Desktop sidebar — sticky, always visible at lg+. --}}
        <aside class="sticky top-0 hidden h-screen w-sidebar shrink-0 flex-col bg-teal-900 lg:flex">
            @include('layouts.partials.admin-nav')
        </aside>

        {{-- Mobile drawer — off-canvas below 1024px. Same nav content. --}}
        <div
            x-show="drawerOpen"
            x-cloak
            class="fixed inset-0 z-50 lg:hidden"
            role="dialog"
            aria-modal="true"
            aria-label="Admin navigation"
        >
            <div
                x-show="drawerOpen"
                x-transition.opacity
                class="fixed inset-0 bg-neutral-900/40"
                x-on:click="drawerOpen = false"
                aria-hidden="true"
            ></div>

            <div
                x-show="drawerOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="-translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-standard duration-150"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full"
                x-trap.noscroll="drawerOpen"
                x-on:keydown.escape.window="drawerOpen = false"
                tabindex="-1"
                class="relative flex h-full w-sidebar flex-col bg-teal-900"
            >
                @include('layouts.partials.admin-nav')
            </div>
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="border-b border-neutral-200 bg-neutral-0">
                <div class="flex items-center gap-3 px-4 py-3 sm:px-6">
                    <button
                        type="button"
                        x-on:click="drawerOpen = true"
                        class="-ml-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-sm text-neutral-600 hover:bg-neutral-100 lg:hidden"
                        aria-label="Open navigation"
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="6" x2="20" y2="6"></line><line x1="4" y1="12" x2="20" y2="12"></line><line x1="4" y1="18" x2="20" y2="18"></line></svg>
                    </button>

                    <div class="min-w-0 flex-1">
                        <x-breadcrumbs :items="$breadcrumbs ?? []" />
                        <h1 class="truncate text-base font-semibold text-neutral-900">@yield('heading', 'Administration')</h1>
                    </div>

                    @auth
                        <div class="flex items-center gap-3">
                            <div class="hidden text-right text-sm sm:block">
                                <p class="font-medium leading-tight text-neutral-900">{{ auth()->user()->name }}</p>
                                <p class="leading-tight text-neutral-500">{{ auth()->user()->role->label() }}</p>
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
