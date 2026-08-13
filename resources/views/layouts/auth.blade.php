{{--
    AUTH LAYOUT — login, register, password reset, verification, activation.

    Every Fortify screen renders through this (C-06, ADR-013). Fortify ships
    no markup of its own; the LMS owns the entire interface.

    Organisation name comes from BrandingService, not config or a hardcoded
    string, so V2 can make it per-organisation without touching this file
    (rule S-1).

    LAYOUT (docs/UI-GUIDE.md §7): a split screen. The left panel is the brand
    statement on deep teal; the right holds a 400px form column. Below 1024px
    the brand panel is dropped entirely rather than stacked — it is decorative,
    and on a phone the only thing that matters is the form.
--}}
@php($branding = app(\App\Services\Settings\BrandingService::class))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Sign in') &middot; {{ $branding->organisationName() }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{--
    data-lms-layout is a test hook, deliberately. C-06 requires every Fortify
    screen to render an LMS view rather than framework markup, and the test
    that proves it needs something stable to assert against. Page copy is not
    stable — it changed the first time the brand voice was applied, and took
    two tests with it.
--}}
<body data-lms-layout="auth" class="h-full bg-neutral-50 font-sans text-neutral-800 antialiased">
    <div class="flex min-h-full flex-col lg:flex-row">

        {{-- ══ BRAND PANEL ══════════════════════════════════════════════
             Hidden below lg. Deep teal with the M motif anchored bottom
             right, per the reference. --}}
        <aside class="relative hidden overflow-hidden bg-teal-900 px-12 py-11 text-white lg:flex lg:w-[45%] lg:flex-col lg:justify-between">
            {{-- The M angle motif — one per screen, never repeated. --}}
            <div class="m-motif pointer-events-none absolute -right-px -bottom-px size-56" aria-hidden="true"></div>

            <a href="{{ url('/') }}" class="relative inline-block">
                <img
                    src="{{ asset('images/logo-maieutic-reversed.png') }}"
                    alt="{{ $branding->organisationName() }}"
                    class="h-10 w-auto"
                >
            </a>

            <div class="relative max-w-[44ch]">
                <p class="eyebrow text-white/55">The Socratic method</p>
                <p class="mt-4 font-serif text-[2.6rem] leading-[1.18] font-semibold tracking-[-0.015em] text-balance">
                    Answers fade. The questions stay.
                </p>
                <p class="mt-5 text-[0.97rem] leading-relaxed text-white/70">
                    A learning experience built on inquiry, not information dumps.
                </p>
            </div>

            <p class="eyebrow relative text-white/45">{{ $branding->organisationName() }}</p>
        </aside>

        {{-- ══ FORM PANEL ═══════════════════════════════════════════════ --}}
        <main class="flex flex-1 items-center justify-center px-5 py-12 sm:px-8">
            <div class="w-full max-w-[400px] animate-fade-in">

                {{-- The mark, for small screens where the brand panel is gone.
                     Uses the red/teal variant: this side is white. --}}
                <a href="{{ url('/') }}" class="mb-8 inline-block lg:hidden">
                    <img
                        src="{{ asset('images/logo-maieutic.png') }}"
                        alt="{{ $branding->organisationName() }}"
                        class="h-9 w-auto"
                    >
                </a>

                @hasSection('heading')
                    <h1 class="text-[1.95rem]">@yield('heading')</h1>
                @endif
                @hasSection('subheading')
                    <p class="mt-1.5 text-sm text-neutral-500">@yield('subheading')</p>
                @endif

                {{-- Status messages (e.g. "reset link sent", "account ready"). --}}
                @if (session('status'))
                    <div class="mt-6">
                        <x-alert variant="success">{{ session('status') }}</x-alert>
                    </div>
                @endif

                {{--
                    Validation errors are also rendered inline against each
                    field (NFR-UX-05). This summary exists for screen-reader
                    users, who benefit from hearing the count up front rather
                    than discovering errors field by field.
                --}}
                @if ($errors->any())
                    <div class="mt-6">
                        <x-alert variant="danger" :title="trans_choice('There is :count problem with your submission.|There are :count problems with your submission.', $errors->count())">
                            <ul class="mt-1 list-inside list-disc">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </x-alert>
                    </div>
                @endif

                {{-- No card wrapper. The form sits directly on the page — the
                     panel itself is the container, and a card inside a card
                     is the kind of nesting the editorial style avoids. --}}
                <div class="mt-7">
                    @yield('content')
                </div>

                @hasSection('footer')
                    <p class="mt-7 text-sm text-neutral-500">@yield('footer')</p>
                @endif
            </div>
        </main>
    </div>
</body>
</html>
