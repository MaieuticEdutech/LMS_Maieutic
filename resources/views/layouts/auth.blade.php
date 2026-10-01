{{--
    AUTH LAYOUT — login, register, password reset, verification, activation.

    Every Fortify screen renders through this (C-06, ADR-013). Fortify ships
    no markup of its own; the LMS owns the entire interface.

    Organisation name comes from BrandingService, not config or a hardcoded
    string, so V2 can make it per-organisation without touching this file
    (rule S-1).

    LAYOUT (docs/UI-GUIDE.md §7): a split screen. The form is on the LEFT, in
    a 400px column that the logo, the form and the footer all share — one
    left edge, so the eye runs straight down it. The brand statement sits on
    deep teal to the RIGHT. Below 1024px the brand panel is dropped entirely
    rather than stacked — it is decorative, and on a phone the only thing that
    matters is the form.

    The form comes first in the source as well as on screen, so a keyboard or
    screen-reader user reaches the fields without passing through the brand
    panel first.
--}}
@php
    // Organisation identity from BrandingService, never a literal (rule S-1).
    // Everything the layout needs is assigned in this ONE block: a second raw
    // php block in the file stops every directive after it from compiling
    // (the trap layouts/app.blade.php documents).
    $branding = app(\App\Services\Settings\BrandingService::class);

    // Brand-panel content — see the comment on the panel below.
    $features = [
        [
            'title' => 'Hands-on lessons',
            'body' => 'Video, documents and slides, organised into modules you work through at your own pace.',
            'icon' => '<rect x="2" y="4" width="20" height="13" rx="2"></rect><path d="M8 21h8"></path><path d="M12 17v4"></path><path d="m10 8.5 4 2-4 2z"></path>',
        ],
        [
            'title' => 'Skill assessments',
            'body' => 'Quizzes and assessments check what you have actually understood, not just what you watched.',
            'icon' => '<path d="M21.8 10A10 10 0 1 1 17 3.34"></path><path d="m9 11 3 3L22 4"></path>',
        ],
        [
            'title' => 'Progress that resumes',
            'body' => 'Pick up exactly where you stopped, and see how far through each course you are.',
            'icon' => '<path d="M3 3v18h18"></path><path d="m7 14 3-4 3 3 5-6"></path>',
        ],
        [
            'title' => 'Verifiable certificates',
            'body' => 'Finish a course and earn a certificate that anyone can verify online.',
            'icon' => '<circle cx="12" cy="8" r="6"></circle><path d="M15.48 12.89 17 22l-5-3-5 3 1.52-9.11"></path>',
        ],
    ];

    // Illustrative subject areas, not a catalogue query. Keep these in
    // line with the categories the organisation actually publishes.
    $tracks = ['Programming', 'Cloud', 'Data', 'Cybersecurity', 'DevOps'];
@endphp
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
    <div class="flex min-h-full">

        {{-- ══ FORM PANEL ═══════════════════════════════════════════════ --}}
        {{-- Full width below lg; from lg a fixed column just wide enough for
             the 400px form and its gutters, so the brand panel — not empty
             page — takes up every remaining pixel. --}}
        <main class="flex flex-1 justify-center px-5 sm:px-8 lg:w-[520px] lg:flex-none lg:shrink-0 xl:w-[600px]">
            <div class="flex w-full max-w-[400px] flex-col py-8 lg:py-10">

                {{-- The red/teal variant: this side is a light surface. 48px
                     tall is ~97px wide — the raster's ~100px ceiling
                     (docs/UI-GUIDE.md, logo size table). The file carries its
                     own whitespace, so anything smaller reads as a speck. --}}
                <a href="{{ url('/') }}" class="self-start rounded-xs focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600">
                    <img
                        src="{{ asset('images/logo-maieutic.png') }}"
                        alt="{{ $branding->organisationName() }}"
                        class="h-12 w-auto"
                    >
                </a>

                <div class="flex flex-1 flex-col justify-center py-12 animate-fade-in">
                    {{-- Optional, and only the login screen defines it today. The
                         brand's type signature is a wide-tracked mono eyebrow set
                         against a serif headline; a screen that wants that contrast
                         can have it without every auth screen gaining one. --}}
                    @hasSection('eyebrow')
                        <p class="eyebrow mb-3 text-teal-600">@yield('eyebrow')</p>
                    @endif

                    @hasSection('heading')
                        <h1 class="text-[2rem] leading-tight tracking-[-0.015em]">@yield('heading')</h1>
                    @endif
                    @hasSection('subheading')
                        <p class="mt-2 text-[0.95rem] leading-relaxed text-neutral-500">@yield('subheading')</p>
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
                    <div class="mt-8">
                        @yield('content')
                    </div>

                    {{-- A hairline, not a box: the secondary route (create an
                         account, back to sign-in) reads as separate from the
                         form without competing with its one primary button. --}}
                    @hasSection('footer')
                        <p class="mt-8 border-t border-neutral-200 pt-6 text-sm text-neutral-500">@yield('footer')</p>
                    @endif
                </div>

                <p class="text-xs text-neutral-500">&copy; {{ now()->year }} {{ $branding->organisationName() }}</p>
            </div>
        </main>

        {{-- ══ BRAND PANEL ══════════════════════════════════════════════
             Hidden below lg. Deep teal with the M motif anchored bottom
             right. It carries no logo: the logo already heads the form
             column, and one mark per screen is the rule the motif follows
             too.

             WIDTH: everything the form column does not need (flex-1). The
             form is a fixed 400px, so any percentage split left a band of
             empty page between the form and the panel that widened with the
             screen. The form side is now fixed (520px, 600px from xl) and the
             panel absorbs the rest; its content is centred inside it so a
             very wide panel does not open the same gap on its own right.

             EVERY FEATURE CLAIMED HERE EXISTS IN THE PRODUCT — lesson types
             (LessonType), assessments, resumable progress (LessonProgress)
             and publicly verifiable certificates (VerifyCertificateController).
             A sign-in page is the first thing a learner reads; it must not
             promise something the platform does not do. Remove a card if
             its feature is ever withdrawn. --}}
        <aside class="relative hidden overflow-hidden bg-teal-900 px-14 py-12 text-white lg:flex lg:flex-1 lg:flex-col lg:justify-center 2xl:px-20 2xl:py-16">
            {{-- The M angle motif — one per screen, never repeated. --}}
            <div class="m-motif pointer-events-none absolute -right-px -bottom-px size-44" aria-hidden="true"></div>

            <div class="relative mx-auto w-full max-w-[46rem]">
                <p class="eyebrow text-teal-300">IT skills, built properly</p>
                <p class="mt-4 max-w-[18ch] font-serif text-[2.25rem] leading-[1.12] font-semibold tracking-[-0.015em] text-balance 2xl:text-[2.9rem]">
                    Build the IT skills that move your career forward.
                </p>
                {{-- Short brand-red rule under the headline — the one accent
                     colour on the panel, echoing the red in the motif. --}}
                <span class="mt-5 block h-1 w-12 rounded-full bg-red-600 2xl:mt-6" aria-hidden="true"></span>
                <p class="mt-5 max-w-[50ch] 2xl:mt-6 text-base leading-relaxed text-white/70">
                    Structured courses you learn at your own pace: practical lessons,
                    real assessments, and a certificate to show for it.
                </p>

                {{-- A compact icon list until 2xl, cards in a 2×2 grid above
                     it. Below ~1536px the panel is too narrow for two cards
                     side by side, and four stacked cards push a 768–800px
                     laptop screen into scrolling a sign-in page. --}}
                <ul class="mt-8 grid gap-5 2xl:mt-10 2xl:grid-cols-2 2xl:gap-3" role="list">
                    @foreach ($features as $feature)
                        <li class="flex gap-4 2xl:flex-col 2xl:gap-0 2xl:rounded-card 2xl:bg-white/5 2xl:p-5 2xl:ring-1 2xl:ring-inset 2xl:ring-white/10">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-control bg-teal-500/20 text-teal-300" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    {!! $feature['icon'] !!}
                                </svg>
                            </span>
                            <div class="2xl:mt-4">
                                <p class="text-sm font-semibold text-white">{{ $feature['title'] }}</p>
                                <p class="mt-1 text-sm leading-relaxed text-white/65">{{ $feature['body'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-8 hidden flex-wrap items-center gap-2 2xl:flex">
                    <span class="eyebrow mr-1 text-white/50">Tracks</span>
                    @foreach ($tracks as $track)
                        <span class="rounded-full px-3 py-1 text-xs font-medium text-white/80 ring-1 ring-inset ring-white/15">{{ $track }}</span>
                    @endforeach
                </div>
            </div>
        </aside>
    </div>
</body>
</html>
