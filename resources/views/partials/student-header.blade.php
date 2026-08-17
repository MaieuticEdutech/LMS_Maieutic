{{--
    The signed-in student header, as drawn in
    `sample student ui/Maieutic LMS.dc.html`: 64px, sticky, translucent white
    over the warm page, logo → dashboard, text-button nav, a search field
    pushed right, and an avatar disc.

    ═════════════════════════════════════════════════════════════════════════
    ONE DEFINITION, TWO LAYOUTS.

    Included by `layouts.student` and — for a signed-in student — by
    `layouts.public`, because the catalogue is a public page that students also
    browse. Without this partial the same header would exist twice and would
    drift the first time either changed. A student clicking "Explore" must not
    watch their navigation disappear.
    ═════════════════════════════════════════════════════════════════════════

    THE MOCKUP'S MEASUREMENTS ARE REPRODUCED, NOT ROUNDED. Both this file and
    the mockup derive from the same Maieutic design system, so nearly every
    value IS a token here already — #00615c is teal-600, #faf9f6 is neutral-50,
    1240px is container-content, the fonts match exactly. Where the design uses
    a value off the 4px grid (34px avatar, 14px nav padding) it is written as an
    arbitrary value rather than snapped to the nearest step.

    ONE THING FROM THE MOCKUP IS STILL ABSENT: the notifications bell. There is
    no notification centre, and a bell that never rings promises something the
    product cannot do. It belongs with the phase that builds it.

    Rendering a link here is presentation only. Every route behind it is
    independently authorised server-side — hiding a link is never the control
    (Development Rule 20, FR-RBAC-02).
--}}
@php($branding = app(\App\Services\Settings\BrandingService::class))
@php($me = auth()->user())

<header class="sticky top-0 z-50 flex h-16 items-center gap-4 border-b border-neutral-200 bg-white/92 px-5 backdrop-blur-[8px] lg:gap-8 lg:px-10">
    <a href="{{ route('student.home') }}" class="shrink-0" aria-label="{{ $branding->organisationName() }} — dashboard">
        <img src="{{ asset('images/logo-maieutic.png') }}"
             alt="{{ $branding->organisationName() }}"
             class="h-8 w-auto">
    </a>

    {{-- Two items, written out rather than looped. A loop needs the array
         declared in a php block, and a raw php block in a layout file has
         already once stopped every directive after it from compiling. --}}
    <nav class="flex gap-2" aria-label="Primary">
        <a href="{{ route('catalogue.index') }}"
           @if (request()->routeIs('catalogue.*')) aria-current="page" @endif
           class="rounded-sm px-[14px] py-2 text-sm font-medium transition-colors {{ request()->routeIs('catalogue.*') ? 'bg-teal-50 text-teal-600' : 'text-neutral-700 hover:bg-neutral-100' }}">
            Explore
        </a>

        <a href="{{ route('student.courses.index') }}"
           @if (request()->routeIs('student.courses.index')) aria-current="page" @endif
           class="rounded-sm px-[14px] py-2 text-sm font-medium transition-colors {{ request()->routeIs('student.courses.index') ? 'bg-teal-50 text-teal-600' : 'text-neutral-700 hover:bg-neutral-100' }}">
            My Learning
        </a>

        {{-- Student-only: the route is behind `role:student`, so showing it to
             an instructor browsing the catalogue would be a link to a 403.
             Hiding it is presentation; the middleware is the control. --}}
        @if (auth()->user()?->isStudent())
            <a href="{{ route('student.certificates.index') }}"
               @if (request()->routeIs('student.certificates.*')) aria-current="page" @endif
               class="rounded-sm px-[14px] py-2 text-sm font-medium transition-colors {{ request()->routeIs('student.certificates.*') ? 'bg-teal-50 text-teal-600' : 'text-neutral-700 hover:bg-neutral-100' }}">
                Certificates
            </a>
        @endif
    </nav>

    {{-- A plain GET form, not a Livewire input: this header renders above every
         screen, most of which are not the catalogue, so there is no component
         here to bind to. Submitting lands on the catalogue with ?q= already
         applied — the same URL its own search box produces, so the two cannot
         disagree. --}}
    <form method="GET"
          action="{{ route('catalogue.index') }}"
          role="search"
          class="relative ml-auto hidden max-w-[420px] flex-1 items-center md:flex">
        <label for="header-search" class="sr-only">Search for courses</label>

        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
             class="pointer-events-none absolute left-[14px] text-neutral-500">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.3-4.3"></path>
        </svg>

        <input id="header-search"
               type="search"
               name="q"
               value="{{ request()->routeIs('catalogue.index') ? request()->query('q') : '' }}"
               placeholder="Search for courses, skills, or topics"
               class="h-10 w-full rounded-sm border border-neutral-200 bg-neutral-50 pl-10 pr-[14px] text-sm text-neutral-900 placeholder:text-neutral-500">
    </form>

    <div class="ml-auto flex items-center gap-4 md:ml-0">
        {{-- The disc is the profile link, exactly as in the mockup. Its
             initials are decorative — the accessible name says where it goes,
             which is what a screen-reader user needs from it. --}}
        <a href="{{ route('profile.show') }}"
           class="flex h-[34px] w-[34px] items-center justify-center rounded-full bg-teal-600 text-[13px] font-semibold text-white transition-colors hover:bg-teal-700"
           @if (request()->routeIs('profile.*')) aria-current="page" @endif>
            <span aria-hidden="true">{{ $me?->initials() }}</span>
            <span class="sr-only">Your profile</span>
        </a>
    </div>
</header>
