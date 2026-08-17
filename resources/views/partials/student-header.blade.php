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

    The bell is a non-interactive mark, exactly as the prototype has it — see
    the note beside it.

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
        {{-- The bell.

             NOT A BUTTON, and that is how the design has it too: in the
             prototype it is a bare <svg> with no onClick, so reproducing it
             exactly means a non-interactive mark rather than a control that
             does nothing when clicked. There is no notification centre behind
             it yet; when there is, this becomes a link and gains a count. --}}
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="text-neutral-600">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
        </svg>

        {{--
            THE AVATAR IS A MENU, NOT A LINK, AND THAT IS A DEPARTURE WORTH
            EXPLAINING.

            The prototype draws the disc as a plain circle with no menu, and the
            first version of this header copied that literally — which left the
            product with NO VISIBLE WAY TO SIGN OUT from the dashboard, My
            Learning, Certificates or the player. Logout existed only at the
            bottom of the profile page, reachable if you already guessed the disc
            led there.

            A static mockup cannot depict an open dropdown; that is a limitation
            of the artefact, not a design decision that sign-out should be
            hidden. The disc looks exactly as drawn — 34px, teal, initials — and
            opens the menu every product of this kind puts behind it.
        --}}
        <div class="relative" x-data="{ open: false }">
            <button type="button"
                    x-on:click="open = ! open"
                    x-on:click.outside="open = false"
                    x-on:keydown.escape.window="open = false"
                    x-bind:aria-expanded="open ? 'true' : 'false'"
                    aria-haspopup="true"
                    class="flex h-[34px] w-[34px] items-center justify-center rounded-full bg-teal-600 text-[13px] font-semibold text-white transition-colors hover:bg-teal-700">
                <span aria-hidden="true">{{ $me?->initials() }}</span>
                <span class="sr-only">Your account</span>
            </button>

            <div x-show="open"
                 x-cloak
                 x-transition.opacity.duration.150ms
                 class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-card border border-neutral-200 bg-white shadow-[0_6px_20px_-6px_rgba(26,24,21,0.1)]">

                {{-- Who you are signed in as. Worth stating: on a shared machine
                     it is the difference between signing out and wondering why
                     someone else's courses are listed. --}}
                <div class="border-b border-neutral-100 px-4 py-3">
                    <div class="truncate text-sm font-semibold text-neutral-900">{{ $me?->name }}</div>
                    <div class="truncate text-[12.5px] text-neutral-500">{{ $me?->email }}</div>
                </div>

                <a href="{{ route('profile.show') }}"
                   class="block px-4 py-2.5 text-sm text-neutral-700 transition-colors hover:bg-neutral-50">
                    Profile
                </a>

                @if ($me?->isStudent())
                    <a href="{{ route('student.certificates.index') }}"
                       class="block px-4 py-2.5 text-sm text-neutral-700 transition-colors hover:bg-neutral-50">
                        Certificates
                    </a>
                @endif

                {{-- A POST form, not a link. Logout changes state, and a GET
                     route for it can be triggered by anything that prefetches a
                     URL — a browser, a chat client unfurling a link, an email
                     scanner — signing people out at random. --}}
                <form method="POST" action="{{ route('logout') }}" class="border-t border-neutral-100">
                    @csrf
                    <button type="submit"
                            class="block w-full px-4 py-2.5 text-left text-sm font-medium text-red-600 transition-colors hover:bg-red-50">
                        Log out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
