{{--
    ADMINISTRATOR LAYOUT — super_admin only.

    Used by the Administrator Area (Phases 4-6, 8, 12, 13).

    Every route rendered inside this layout sits behind
    ['auth', 'active', 'role:super_admin'] AND an explicit policy check on the
    specific record. This layout being reachable proves nothing about
    authorisation — it is presentation only (Development Rule 20).

    $breadcrumbs — optional list of ['label' => string, 'url' => string|null],
    rendered via <x-breadcrumbs>. Pages that don't pass one simply show none.

    NAV ITEMS ARE ADDED ONE CHECKPOINT AT A TIME, deliberately never all at
    once: a nav link to a route that doesn't exist yet is a broken link waiting
    to happen. Add the entry in the same checkpoint that registers its route.

    ─────────────────────────────────────────────────────────────────────────
    BRAND PASS, 2026-08-13 — restyle only, no behavioural change.

    Brought in line with docs/UI-GUIDE.md §7: a 248px `teal-900` sidebar
    carrying the reversed logo, warm `neutral-*` surfaces in place of the
    placeholder `zinc-*`, and serif headings.

    ALSO FIXES A REAL GAP RATHER THAN A COSMETIC ONE. The sidebar was
    `hidden lg:block` with nothing behind it, so below 1024px the admin
    navigation simply disappeared — an administrator on a tablet had no way to
    move between sections. It is now a focus-trapped drawer, per NFR-UX-02.
    ─────────────────────────────────────────────────────────────────────────
--}}
@php($branding = app(\App\Services\Settings\BrandingService::class))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Administration') &middot; {{ $branding->organisationName() }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body data-lms-layout="admin" class="min-h-full bg-neutral-50 font-sans text-neutral-800 antialiased">
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-control focus:bg-teal-600 focus:px-4 focus:py-2 focus:text-white">
        Skip to content
    </a>

    {{-- The nav lives in one partial, included by both the desktop rail and
         the mobile drawer, so the two cannot drift apart. --}}
    <div x-data="{ drawer: false }" class="flex min-h-full">

        {{-- ══ DESKTOP RAIL ══ 248px, per the reference. ══════════════════ --}}
        <aside class="hidden w-sidebar shrink-0 flex-col bg-teal-900 lg:flex">
            <div class="border-b border-white/12 px-5 py-5">
                <a href="{{ route('admin.dashboard') }}" class="inline-block">
                    <img src="{{ asset('images/logo-maieutic-reversed.png') }}"
                         alt="{{ $branding->organisationName() }}"
                         class="h-8 w-auto">
                </a>
                <p class="eyebrow mt-3 text-white/45">Administration</p>
            </div>

            @include('layouts.partials.admin-nav')
        </aside>

        {{-- ══ MOBILE DRAWER ══ below lg. ════════════════════════════════ --}}
        <div x-show="drawer" x-cloak class="fixed inset-0 z-40 lg:hidden">
            <div x-show="drawer" x-transition.opacity
                 class="fixed inset-0 bg-neutral-900/40 backdrop-blur-[2px]"
                 x-on:click="drawer = false" aria-hidden="true"></div>

            <aside x-show="drawer"
                   x-transition:enter="transition ease-out duration-200"
                   x-transition:enter-start="-translate-x-full"
                   x-transition:enter-end="translate-x-0"
                   x-transition:leave="transition ease-in duration-150"
                   x-transition:leave-start="translate-x-0"
                   x-transition:leave-end="-translate-x-full"
                   x-trap.noscroll="drawer"
                   x-on:keydown.escape.window="drawer = false"
                   class="relative flex h-full w-sidebar max-w-[85vw] flex-col bg-teal-900"
                   role="dialog" aria-modal="true" aria-label="Admin navigation">
                <div class="flex items-start justify-between border-b border-white/12 px-5 py-5">
                    <div>
                        <img src="{{ asset('images/logo-maieutic-reversed.png') }}"
                             alt="{{ $branding->organisationName() }}"
                             class="h-8 w-auto">
                        <p class="eyebrow mt-3 text-white/45">Administration</p>
                    </div>
                    <button type="button" x-on:click="drawer = false"
                            class="rounded-control p-1 text-white/70 transition-colors hover:bg-white/8 hover:text-white">
                        <span class="sr-only">Close navigation</span>
                        <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                @include('layouts.partials.admin-nav')
            </aside>
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="border-b border-neutral-200 bg-white">
                <div class="flex items-center justify-between gap-4 px-4 py-4 sm:px-6">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" x-on:click="drawer = true"
                                class="-ml-1 rounded-control p-2 text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 lg:hidden">
                            <span class="sr-only">Open navigation</span>
                            <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <path d="M3 5h14M3 10h14M3 15h14" stroke-linecap="round"/>
                            </svg>
                        </button>

                        <div class="min-w-0">
                            <x-breadcrumbs :items="$breadcrumbs ?? []" />
                            <h1 class="truncate text-xl">@yield('heading', 'Administration')</h1>
                        </div>
                    </div>

                    @auth
                        <div class="flex shrink-0 items-center gap-3">
                            {{-- Identity is hidden on the narrowest screens: the
                                 heading and the nav trigger matter more than a
                                 name the user already knows. --}}
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
