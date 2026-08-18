{{--
    LANDING LAYOUT — the marketing page only.

    ═════════════════════════════════════════════════════════════════════════
    WHY THIS EXISTS SEPARATELY FROM layouts.public.

    `layouts.public` carries the APPLICATION's chrome — a compact bar with a
    catalogue link and a sign-in, and a one-line footer. Every catalogue and
    course page keeps using it, and a second copy of that header would be a
    defect (docs/UI-GUIDE.md §8 — extend, don't fork).

    The landing page's header is a different object: sticky, translucent over
    the paper background, with in-page section anchors that exist nowhere else
    in the product. That is a marketing surface, not an app screen. So this
    layout supplies no header and no footer — the page brings its own.

    Nothing else may use this layout. A second page wanting this chrome is a
    signal the two should share a component, not this file.
    ═════════════════════════════════════════════════════════════════════════

    ═════════════════════════════════════════════════════════════════════════
    THE DESIGN SYSTEM'S OWN VARIABLE NAMES ARE DEFINED BELOW, DELIBERATELY.

    The page is a faithful reproduction of the Design-Compiler export in
    `sample landing ui/`, which styles every element inline against variables
    like `var(--text-muted)` and `var(--fs-5xl)`. Rewriting those into Tailwind
    utilities was tried first and produced a page that was approximately right
    and specifically wrong — rounded type sizes, near-miss spacing.

    So the export's markup is kept verbatim and its variables are declared
    here instead. Every VALUE maps onto the app's existing theme tokens rather
    than repeating a hex code, so a brand change in app.css still reaches this
    page. The names differ; the source of truth does not.

    Scoped to this layout, which only the landing page uses, so none of it can
    leak into the application's own screens.
    ═════════════════════════════════════════════════════════════════════════
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', app(\App\Services\Settings\BrandingService::class)->organisationName())</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        :root {
            /* Families — self-hosted by the app, not fetched from Google. */
            --font-serif: 'Newsreader', Georgia, 'Times New Roman', serif;
            --font-sans: 'Hanken Grotesk', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --font-mono: 'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace;

            --fw-medium: 500;
            --fw-semibold: 600;

            /* Type scale, exactly as the design states it. */
            --fs-eyebrow: 12px;
            --fs-sm: 14px;
            --fs-base: 16px;
            --fs-lg: 18px;
            --fs-xl: 20px;
            --fs-2xl: 24px;
            --fs-4xl: 38px;
            --fs-5xl: 48px;
            --fs-6xl: 64px;

            --lh-tight: 1.05;
            --lh-snug: 1.15;
            --lh-heading: 1.2;
            --lh-normal: 1.5;
            --lh-relaxed: 1.65;

            --ls-tight: -0.015em;
            --ls-eyebrow: 0.16em;

            /* Palette — pointed at the app's tokens, never re-stated as hex. */
            --teal-100: var(--color-teal-100);
            --teal-200: var(--color-teal-200);
            --teal-300: var(--color-teal-300);
            --teal-600: var(--color-teal-600);
            --teal-900: var(--color-teal-900);
            --red-600: var(--color-red-600);

            --surface-card: var(--color-neutral-0);
            --text-heading: var(--color-neutral-900);
            --text-body: var(--color-neutral-800);
            --text-muted: var(--color-neutral-500);
            --text-subtle: var(--color-neutral-400);
            --text-brand: var(--color-teal-600);
            --text-inverse: var(--color-neutral-0);

            --border: var(--color-neutral-200);
            --border-strong: var(--color-neutral-300);
            --border-inverse: rgba(255, 255, 255, 0.16);

            --radius-lg: 16px;
            --shadow-sm: 0 1px 3px rgba(26, 24, 21, 0.06), 0 1px 2px rgba(26, 24, 21, 0.04);
        }

        body {
            margin: 0;
            background: #faf9f6;
            color: #2b2825;
            font-family: 'Hanken Grotesk', system-ui, sans-serif;
        }

        html { scroll-behavior: smooth; }

        /*
         * :where() so these contribute ZERO specificity.
         *
         * Written first as `.landing a { color: … }`, which scores 0-1-1 and
         * therefore beat Tailwind's `.text-white` at 0-1-0 — so the primary
         * button rendered teal text on a teal fill and its label vanished
         * entirely. A link colour must never outrank a component's own
         * colour; :where() makes that structurally impossible rather than a
         * thing to remember.
         */
        /*
         * ═══════════════════════════════════════════════════════════════════
         * THERE IS NO BLANKET `a { color }` RULE HERE, AND THAT IS THE FIX.
         *
         * The export carries one. Reproduced here it made the header's Sign in
         * button render teal text on a teal fill — an invisible label — and it
         * took three attempts to understand why, because the cause is not
         * specificity.
         *
         * Tailwind 4 emits every utility inside `@layer utilities`. A <style>
         * block in this document is UNLAYERED. Unlayered CSS beats layered CSS
         * outright, whatever the selectors score — so `.text-white` inside the
         * layer could never win, and `:where()` was solving a problem that was
         * never the problem.
         *
         * The rule is deleted rather than out-specified because it was never
         * needed: the export gives every link an inline colour of its own —
         * nav links --text-body, footer links --text-muted, the hero's second
         * action --teal-100 — and buttons carry theirs as utilities. A global
         * link colour had nothing left to style and one thing left to break.
         *
         * Hover stays, scoped to the two places real text links live, so it
         * cannot reach a button.
         * ═══════════════════════════════════════════════════════════════════
         */
        .landing nav a:hover,
        .landing footer a:hover { text-decoration: underline; text-underline-offset: 0.18em; }

        .landing h1, .landing h2, .landing h3 { margin: 0; text-wrap: balance; }
        .landing p { margin: 0; text-wrap: pretty; }

        .landing .grid-card:hover { border-color: var(--border-strong); box-shadow: var(--shadow-sm); }

        /* The hero's second action, on the dark teal panel. The shared ghost
           button variant is built for light surfaces and would be all but
           invisible there. */
        .landing .l-ghost-dark:hover { background: rgba(255, 255, 255, 0.08); color: #fff; text-decoration: none; }

        /*
         * The ONLY departure from the export, and it is not optional.
         *
         * The design wraps everything in `min-width:1100px`, which is fine for
         * a desktop mock and unusable as a website: on a phone it produces a
         * page that scrolls sideways. These rules stack the two-column grids
         * below 900px and step the display type down. Nothing else changes —
         * above 900px this page is the export, pixel for pixel.
         */
        @media (max-width: 900px) {
            .landing .l-split { grid-template-columns: 1fr !important; gap: 40px !important; }
            .landing .l-hero-h1 { font-size: 40px !important; }
            .landing .l-nav { display: none !important; }
            .landing .l-section { padding-top: 56px !important; padding-bottom: 56px !important; }
            .landing .l-media { height: 260px !important; }

            /* The hero photograph keeps its own 4:3 ratio at every width.
               The 260px above is right for the feature rows, whose art is
               decorative and can be cropped — but the hero image carries the
               product itself on a laptop screen, and squeezing it into a wide
               260px band on a phone crops that away entirely. */
            .landing .l-media-hero { height: auto !important; aspect-ratio: 4 / 3; }
            .landing .l-flip { order: 0 !important; }
        }

        @media (max-width: 640px) {
            .landing .l-grid-3 { grid-template-columns: 1fr !important; }
            .landing .l-hero-h1 { font-size: 34px !important; }
            .landing .l-display { font-size: 34px !important; }
        }
    </style>
</head>
<body class="min-h-full antialiased">
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60] focus:rounded-sm focus:bg-teal-600 focus:px-4 focus:py-2 focus:text-white">
        Skip to content
    </a>

    <div class="landing">
        @yield('content')
    </div>

    @livewireScripts
</body>
</html>
