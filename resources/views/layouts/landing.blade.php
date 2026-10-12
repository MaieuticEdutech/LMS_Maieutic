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

    STYLING. The landing page is styled by the scoped stylesheet below rather
    than Tailwind utilities, so it can carry motion and composition the
    application screens never need. Every colour points at the app's theme
    tokens in app.css (plus one warm accent, --amber, declared here for the
    marketing surface only), so a brand change still reaches this page.

    Everything is scoped under `.landing`, so none of it can leak into the
    application's own screens. Motion is decorative and switched off under
    prefers-reduced-motion. Content never depends on an animation to appear:
    the reveal classes only act once the page's script adds `.has-reveal`.
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
            --font-serif: 'Newsreader', Georgia, 'Times New Roman', serif;
            --font-sans: 'Hanken Grotesk', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --font-mono: 'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace;

            --teal-50: var(--color-teal-50);
            --teal-100: var(--color-teal-100);
            --teal-200: var(--color-teal-200);
            --teal-300: var(--color-teal-300);
            --teal-400: var(--color-teal-400);
            --teal-500: var(--color-teal-500);
            --teal-600: var(--color-teal-600);
            --teal-700: var(--color-teal-700);
            --teal-800: var(--color-teal-800);
            --teal-900: var(--color-teal-900);
            --red-600: var(--color-red-600);
            --marigold: var(--color-marigold);

            /* The marketing page's one warm accent. */
            --amber: #f6b73c;
            --amber-dark: #e0a02a;
            --amber-ink: #3a2a05;

            --paper: var(--color-neutral-50);
            --card: var(--color-neutral-0);
            --ink: var(--color-neutral-900);
            --body: var(--color-neutral-800);
            --muted: var(--color-neutral-500);
            --subtle: var(--color-neutral-400);
            --line: var(--color-neutral-200);
            --line-strong: var(--color-neutral-300);

            --radius: 20px;
            --radius-sm: 12px;
            --shadow: 0 20px 50px rgba(26, 24, 21, 0.10);
            --shadow-lg: 0 32px 80px rgba(5, 40, 38, 0.28);
            --ease: cubic-bezier(0.2, 0.7, 0.2, 1);
        }

        body { margin: 0; background: var(--paper); color: var(--body); font-family: var(--font-sans); }
        html { scroll-behavior: smooth; }

        .landing h1, .landing h2, .landing h3 { margin: 0; text-wrap: balance; }
        .landing p { margin: 0; text-wrap: pretty; }
        .landing a { color: inherit; text-decoration: none; }
        .landing img { max-width: 100%; }
        .landing .l-wrap { max-width: 1240px; margin: 0 auto; padding: 0 24px; }

        /* ── Type ─────────────────────────────────────────────────────────── */
        .landing .l-eyebrow { display: inline-block; font-family: var(--font-mono); font-size: 12px; letter-spacing: 0.16em; text-transform: uppercase; color: var(--teal-600); }
        .landing .l-eyebrow-light { color: var(--teal-200); }
        .landing .l-h2 { font-family: var(--font-serif); font-weight: 500; font-size: 52px; line-height: 1.08; letter-spacing: -0.02em; color: var(--ink); max-width: 22ch; }
        .landing .l-h2-light { color: #fff; }
        .landing .l-h2-xl { font-size: 68px; }
        .landing .l-h3 { font-family: var(--font-serif); font-weight: 500; font-size: 26px; line-height: 1.2; letter-spacing: -0.01em; color: var(--ink); }
        .landing .l-sub { font-size: 19px; line-height: 1.6; color: var(--muted); max-width: 52ch; }
        .landing .l-sub-light { color: var(--teal-100); }
        .landing .l-body { font-size: 16px; line-height: 1.65; color: var(--muted); }
        .landing .l-head { display: flex; flex-direction: column; gap: 12px; margin-bottom: 40px; }
        .landing .l-head-center { align-items: center; text-align: center; }
        .landing .l-section-pad { padding: 72px 0; }
        .landing .l-section-tint { background: var(--card); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }

        /* ── Buttons ──────────────────────────────────────────────────────── */
        .landing .l-btn { display: inline-flex; align-items: center; justify-content: center; gap: 10px; border-radius: 12px; font-weight: 600; white-space: nowrap; transition: transform 200ms var(--ease), box-shadow 200ms var(--ease), background 150ms; }
        .landing .l-btn-sm { height: 38px; padding: 0 16px; font-size: 14px; }
        .landing .l-btn-lg { height: 56px; padding: 0 26px; font-size: 16px; }
        .landing .l-btn-teal { background: var(--teal-600); color: #fff; box-shadow: 0 6px 16px rgba(0, 97, 92, 0.25); }
        .landing .l-btn-teal:hover { background: var(--teal-700); transform: translateY(-1px); }
        .landing .l-btn-amber { background: var(--amber); color: var(--amber-ink); box-shadow: 0 10px 28px rgba(246, 183, 60, 0.35); }
        .landing .l-btn-amber:hover { background: var(--amber-dark); transform: translateY(-2px); box-shadow: 0 16px 36px rgba(246, 183, 60, 0.45); }
        .landing .l-btn-amber svg { transition: transform 200ms var(--ease); }
        .landing .l-btn-amber:hover svg { transform: translateX(3px); }
        .landing .l-btn-ghost-light { color: #fff; border: 1.5px solid rgba(255, 255, 255, 0.28); }
        .landing .l-btn-ghost-light:hover { background: rgba(255, 255, 255, 0.1); border-color: rgba(255, 255, 255, 0.5); }

        /* ── Header ───────────────────────────────────────────────────────── */
        /* Header: transparent over the hero with white links in a frosted pill;
           becomes a compact white bar once the hero has scrolled past. */
        .landing .l-header { position: fixed; top: 0; left: 0; right: 0; z-index: 50; background: transparent; border-bottom: 1px solid transparent; transition: background 260ms var(--ease), box-shadow 260ms, border-color 260ms; }
        .landing .l-header-inner { height: 84px; display: flex; align-items: center; justify-content: space-between; gap: 24px; transition: height 260ms var(--ease); }
        .landing .l-logo { display: inline-flex; align-items: center; padding: 8px 14px; border-radius: 14px; background: rgba(255, 255, 255, 0.94); box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18); transition: background 260ms, box-shadow 260ms, padding 260ms; }
        .landing .l-logo img { height: 34px; display: block; }
        .landing .l-nav { display: flex; gap: 4px; padding: 6px; border-radius: 999px; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.18); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); font-size: 15px; font-weight: 500; color: #fff; transition: background 260ms, border-color 260ms, color 260ms; }
        .landing .l-nav a { padding: 9px 16px; border-radius: 999px; transition: background 180ms, color 180ms; }
        .landing .l-nav a:hover { background: rgba(255, 255, 255, 0.16); }
        .landing .l-header-actions { display: flex; align-items: center; gap: 20px; font-size: 15px; font-weight: 500; }
        .landing .l-nav-link { color: #fff; opacity: 0.92; transition: color 260ms, opacity 180ms; }
        .landing .l-nav-link:hover { opacity: 1; text-decoration: underline; text-underline-offset: 0.25em; }
        .landing .l-header.is-scrolled { background: rgba(255, 255, 255, 0.92); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border-bottom-color: var(--line); box-shadow: 0 8px 30px rgba(26, 24, 21, 0.08); }
        .landing .l-header.is-scrolled .l-header-inner { height: 68px; }
        .landing .l-header.is-scrolled .l-logo { background: transparent; box-shadow: none; padding: 0; }
        .landing .l-header.is-scrolled .l-nav { background: var(--teal-50); border-color: transparent; color: var(--ink); }
        .landing .l-header.is-scrolled .l-nav a:hover { background: var(--teal-100); }
        .landing .l-header.is-scrolled .l-nav-link { color: var(--ink); }

        /* ── Hero ─────────────────────────────────────────────────────────── */
        .landing .l-hero { position: relative; overflow: hidden; color: #fff; padding: 140px 0 84px; background: linear-gradient(135deg, var(--teal-900) 0%, var(--teal-800) 45%, var(--teal-700) 100%); }
        .landing .l-blob { position: absolute; border-radius: 50%; filter: blur(60px); pointer-events: none; opacity: 0.55; animation: l-drift-xy 14s ease-in-out infinite; }
        .landing .l-blob-a { width: 560px; height: 560px; left: -160px; top: -200px; background: var(--teal-500); }
        .landing .l-blob-b { width: 640px; height: 640px; right: -180px; top: -120px; background: var(--teal-400); animation-delay: -5s; }
        .landing .l-blob-c { width: 420px; height: 420px; right: 18%; bottom: -260px; background: var(--amber); opacity: 0.28; animation-delay: -9s; }
        .landing .l-blob-d { width: 520px; height: 520px; left: -120px; bottom: -260px; background: var(--teal-500); opacity: 0.45; }
        .landing .l-blob-e { width: 700px; height: 420px; left: 50%; top: 50%; transform: translate(-50%, -50%); background: var(--teal-500); opacity: 0.5; }
        .landing .l-grid-lines { position: absolute; inset: 0; pointer-events: none; opacity: 0.08;
            background-image: linear-gradient(rgba(255,255,255,0.6) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.6) 1px, transparent 1px);
            background-size: 64px 64px; mask-image: radial-gradient(ellipse at 50% 40%, #000 30%, transparent 75%); -webkit-mask-image: radial-gradient(ellipse at 50% 40%, #000 30%, transparent 75%); }
        .landing .l-hero-grid { position: relative; display: grid; grid-template-columns: 6fr 5fr; gap: 64px; align-items: center; }
        .landing .l-hero-copy { display: flex; flex-direction: column; align-items: flex-start; gap: 26px; }
        .landing .l-pill { display: inline-flex; align-items: center; gap: 10px; padding: 8px 14px 8px 10px; border-radius: 999px; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.18); font-size: 13px; font-weight: 500; color: var(--teal-100); backdrop-filter: blur(6px); }
        .landing .l-pill-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--amber); box-shadow: 0 0 0 0 rgba(246, 183, 60, 0.6); animation: l-pulse 2.2s ease-out infinite; }
        .landing .l-hero-h1 { font-family: var(--font-serif); font-weight: 500; font-size: 74px; line-height: 1.04; letter-spacing: -0.02em; color: #fff; }
        .landing .l-hero-h1 em { font-style: italic; color: var(--amber); }
        .landing .l-hero-lead { font-size: 20px; line-height: 1.65; color: var(--teal-100); max-width: 50ch; }
        .landing .l-hero-actions { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-top: 6px; }
        .landing .l-center { justify-content: center; }
        .landing .l-proof { margin: 8px 0 0; padding: 0; list-style: none; display: flex; flex-wrap: wrap; gap: 12px 26px; font-size: 14px; color: var(--teal-100); }
        .landing .l-proof li { display: flex; align-items: center; gap: 8px; }
        .landing .l-proof-check { display: inline-flex; width: 20px; height: 20px; border-radius: 50%; background: rgba(246, 183, 60, 0.18); color: var(--amber); align-items: center; justify-content: center; }

        .landing .l-hero-visual { position: relative; }
        .landing .l-photo { position: relative; aspect-ratio: 4 / 3; border-radius: 26px; overflow: hidden; border: 6px solid rgba(255, 255, 255, 0.92); box-shadow: var(--shadow-lg); transform: rotate(-2.5deg); transition: transform 600ms var(--ease); }
        .landing .l-hero-visual:hover .l-photo { transform: rotate(0deg) scale(1.01); }
        .landing .l-glass { position: absolute; display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 16px; background: rgba(255, 255, 255, 0.94); border: 1px solid rgba(255, 255, 255, 0.8); box-shadow: 0 18px 44px rgba(0, 0, 0, 0.28); backdrop-filter: blur(10px); color: var(--ink); }
        .landing .l-glass-progress { left: -28px; bottom: 36px; width: 290px; }
        .landing .l-glass-quiz { right: -22px; top: 32px; }
        .landing .l-glass-cert { right: 6%; bottom: -26px; }
        .landing .l-glass-label { font-family: var(--font-mono); font-size: 10px; letter-spacing: 0.16em; text-transform: uppercase; color: var(--muted); }
        .landing .l-glass-value { font-family: var(--font-serif); font-size: 28px; line-height: 1; color: var(--ink); margin-top: 2px; }
        .landing .l-glass-value span { font-family: var(--font-sans); font-size: 12px; color: var(--muted); margin-left: 6px; }
        .landing .l-glass-strong { font-size: 14px; font-weight: 600; color: var(--ink); }
        .landing .l-glass-icon { display: inline-flex; width: 38px; height: 38px; border-radius: 12px; align-items: center; justify-content: center; flex: 0 0 auto; }
        .landing .l-glass-icon-teal { background: var(--teal-50); color: var(--teal-600); }
        .landing .l-glass-icon-amber { background: rgba(246, 183, 60, 0.22); color: var(--amber-dark); }
        .landing .l-bar { margin-top: 8px; height: 6px; border-radius: 999px; background: rgba(0, 97, 92, 0.12); overflow: hidden; width: 170px; }
        .landing .l-bar-fill { width: 65%; height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--teal-500), var(--teal-600)); transform-origin: left; }
        .landing .l-ring-fill { stroke-dasharray: 138.2; stroke-dashoffset: 48.4; }
        .landing.has-reveal .l-ring-fill { stroke-dashoffset: 138.2; }
        .landing.has-reveal .is-in .l-ring-fill { animation: l-ring 1100ms var(--ease) 500ms forwards; }
        .landing.has-reveal .l-bar-fill { transform: scaleX(0); }
        .landing.has-reveal .is-in .l-bar-fill { animation: l-bar 1100ms var(--ease) 500ms forwards; }
        .landing .l-float { animation: l-drift 7s ease-in-out infinite; }
        .landing .l-float-slow { animation-duration: 9s; animation-delay: 1.2s; }
        .landing .l-float-slower { animation-duration: 11s; animation-delay: 2.4s; }


        /* ── Journey (the three steps, on one open line) ───────────────────── */
        .landing .l-journey { position: relative; margin: 0; padding: 0; list-style: none; display: grid; grid-template-columns: repeat(3, 1fr); gap: 48px; }
        .landing .l-journey::before { content: ""; position: absolute; left: calc(16.66% + 44px); right: calc(16.66% + 44px); top: 44px; height: 3px; border-radius: 3px; background: var(--line); }
        .landing .l-journey::after { content: ""; position: absolute; left: calc(16.66% + 44px); right: calc(16.66% + 44px); top: 44px; height: 3px; border-radius: 3px; background: linear-gradient(90deg, var(--teal-600), var(--amber), var(--red-600)); transform: scaleX(0); transform-origin: left; }
        .landing.has-reveal .l-journey.is-in::after { animation: l-sweep 1400ms var(--ease) 300ms forwards; }
        .landing:not(.has-reveal) .l-journey::after { transform: none; }
        .landing .l-jstep { position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 12px; padding: 0 12px; }
        .landing .l-jnode { position: relative; width: 88px; height: 88px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; background: var(--card); border: 3px solid var(--card); box-shadow: 0 16px 34px rgba(26, 24, 21, 0.14); transition: transform 300ms var(--ease), box-shadow 300ms var(--ease); }
        .landing .l-jstep:hover .l-jnode { transform: translateY(-6px) scale(1.04); box-shadow: 0 24px 44px rgba(26, 24, 21, 0.18); }
        .landing .l-jicon { display: inline-flex; width: 100%; height: 100%; border-radius: 50%; align-items: center; justify-content: center; }
        .landing .l-jstep-teal .l-jicon { background: linear-gradient(135deg, var(--teal-500), var(--teal-700)); }
        .landing .l-jstep-amber .l-jicon { background: linear-gradient(135deg, #f9c866, var(--amber-dark)); color: var(--amber-ink); }
        .landing .l-jstep-red .l-jicon { background: linear-gradient(135deg, #b4332a, var(--red-600)); }
        .landing .l-jnum { position: absolute; top: -6px; right: -6px; width: 30px; height: 30px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: var(--ink); color: #fff; font-family: var(--font-mono); font-size: 12px; font-weight: 700; border: 3px solid var(--paper); }
        .landing .l-jlabel { margin-top: 10px; font-family: var(--font-mono); font-size: 12px; letter-spacing: 0.16em; text-transform: uppercase; color: var(--muted); }
        .landing .l-jstep .l-h3 { max-width: 16ch; }
        .landing .l-jstep .l-body { max-width: 34ch; }

        /* ── Showcase (feature list + one framed screen) ───────────────────── */
        .landing .l-showcase { display: grid; grid-template-columns: 5fr 7fr; gap: 48px; align-items: center; }
        .landing .l-showcase-list { display: flex; flex-direction: column; gap: 6px; }
        .landing .l-showcase-tab { position: relative; overflow: hidden; display: grid; grid-template-columns: auto 1fr; gap: 18px; align-items: start; text-align: left; padding: 22px 22px 24px; border: 0; border-radius: var(--radius-sm); background: transparent; cursor: pointer; font: inherit; color: inherit; transition: background 200ms, transform 200ms var(--ease); }
        .landing .l-showcase-tab:hover { background: rgba(26, 24, 21, 0.03); }
        .landing .l-showcase-tab.is-active { background: var(--card); box-shadow: 0 14px 36px rgba(26, 24, 21, 0.08); transform: translateX(6px); }
        .landing .l-showcase-tab:focus-visible { outline: 3px solid var(--teal-300); outline-offset: 2px; }
        .landing .l-showcase-icon { display: inline-flex; width: 48px; height: 48px; border-radius: 14px; align-items: center; justify-content: center; background: var(--teal-50); color: var(--teal-600); transition: background 200ms, color 200ms; }
        .landing .l-showcase-tab-amber .l-showcase-icon { background: rgba(246, 183, 60, 0.2); color: var(--amber-dark); }
        .landing .l-showcase-tab-red .l-showcase-icon { background: #fbeae9; color: var(--red-600); }
        .landing .l-showcase-tab.is-active .l-showcase-icon { color: #fff; }
        .landing .l-showcase-tab-teal.is-active .l-showcase-icon { background: var(--teal-600); }
        .landing .l-showcase-tab-amber.is-active .l-showcase-icon { background: var(--amber); color: var(--amber-ink); }
        .landing .l-showcase-tab-red.is-active .l-showcase-icon { background: var(--red-600); }
        .landing .l-showcase-text { display: flex; flex-direction: column; gap: 6px; }
        .landing .l-showcase-title { font-family: var(--font-serif); font-size: 24px; line-height: 1.2; color: var(--ink); }
        .landing .l-showcase-body { font-size: 15px; line-height: 1.6; color: var(--muted); max-height: 0; opacity: 0; overflow: hidden; transition: max-height 360ms var(--ease), opacity 300ms; }
        .landing .l-showcase-tab.is-active .l-showcase-body { max-height: 120px; opacity: 1; }
        .landing .l-showcase-progress { position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: var(--teal-600); transform: scaleX(0); transform-origin: left; }
        .landing .l-showcase-tab-amber .l-showcase-progress { background: var(--amber); }
        .landing .l-showcase-tab-red .l-showcase-progress { background: var(--red-600); }
        .landing .l-showcase.is-auto .l-showcase-tab.is-active .l-showcase-progress { animation: l-sweep 6000ms linear forwards; }
        .landing .l-showcase-stage { position: relative; min-height: 380px; }
        .landing .l-showcase-panel { border-radius: var(--radius); overflow: hidden; }
        .landing .l-showcase-panel.is-active .l-device { animation: l-rise 420ms var(--ease); }
        .landing .l-showcase-panel-teal .l-device { background: linear-gradient(135deg, var(--teal-100), var(--teal-50)); }
        .landing .l-showcase-panel-amber .l-device { background: linear-gradient(135deg, #fde6b3, var(--marigold)); }
        .landing .l-showcase-panel-red .l-device { background: linear-gradient(135deg, #f5cbc9, #fbeae9); }
        .landing .l-showcase-panel .l-device { padding: 40px 0 0 40px; border-radius: var(--radius); }
        .landing .l-showcase-panel .l-device-screen { height: 340px; border-radius: 0; }

        /* ── Strip (three more facts, no boxes) ───────────────────────────── */
        .landing .l-strip { margin: 40px auto 0; padding: 22px 0 0; list-style: none; display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; border-top: 1px solid var(--line); }
        .landing .l-strip li { display: flex; align-items: flex-start; gap: 14px; font-size: 15px; color: var(--body); }
        .landing .l-strip strong { display: block; font-weight: 600; color: var(--ink); }
        .landing .l-strip-body { display: block; color: var(--muted); margin-top: 2px; }
        .landing .l-strip-icon { display: inline-flex; width: 42px; height: 42px; border-radius: 12px; align-items: center; justify-content: center; background: var(--teal-50); color: var(--teal-600); flex: 0 0 auto; }

        .landing .l-ticks { margin: 4px 0 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 10px; font-size: 15px; color: var(--body); }
        .landing .l-ticks li { display: flex; align-items: center; gap: 12px; }
        .landing .l-ticks-light { color: var(--teal-100); }
        .landing .l-tick { display: inline-flex; width: 22px; height: 22px; border-radius: 50%; background: var(--teal-50); color: var(--teal-600); align-items: center; justify-content: center; flex: 0 0 auto; }
        .landing .l-tick-light { background: rgba(255, 255, 255, 0.14); color: var(--amber); }

        /* Device frame: a browser window on a tinted backdrop. */
        .landing .l-device { position: relative; padding: 36px 0 0 36px; border-radius: 0 var(--radius) var(--radius) 0; overflow: hidden; }
        .landing .l-device-top { padding: 32px 32px 0; border-radius: var(--radius) var(--radius) 0 0; }
        .landing .l-device-teal { background: linear-gradient(135deg, var(--teal-100), var(--teal-50)); }
        .landing .l-device-amber { background: linear-gradient(135deg, #fde6b3, var(--marigold)); }
        .landing .l-device-red { background: linear-gradient(135deg, #f5cbc9, #fbeae9); }
        .landing .l-device-bar { display: flex; gap: 6px; padding: 10px 12px; background: #fff; border: 1px solid var(--line); border-bottom: 0; border-radius: 12px 12px 0 0; width: fit-content; min-width: 100%; box-sizing: border-box; }
        .landing .l-device-bar span { width: 9px; height: 9px; border-radius: 50%; background: var(--line); }
        .landing .l-device-bar span:first-child { background: #e79a96; }
        .landing .l-device-bar span:nth-child(2) { background: var(--amber); }
        .landing .l-device-bar span:nth-child(3) { background: var(--teal-300); }
        .landing .l-device-screen { position: relative; height: 320px; border: 1px solid var(--line); border-bottom: 0; border-radius: 0; overflow: hidden; background: #fff; box-shadow: 0 18px 40px rgba(26, 24, 21, 0.12); }
        .landing .l-device-top .l-device-screen { height: 220px; }
        .landing .l-device-screen img { transition: transform 700ms var(--ease); }
        .landing .l-showcase-panel:hover .l-device-screen img { transform: scale(1.03); }

        /* ── Quote ────────────────────────────────────────────────────────── */
        .landing .l-quote { position: relative; overflow: hidden; background: var(--marigold); padding: 80px 0; text-align: center; }
        .landing .l-quote .l-wrap { display: flex; flex-direction: column; align-items: center; gap: 22px; }
        .landing .l-quote-text { margin: 0; font-family: var(--font-serif); font-size: 46px; line-height: 1.2; letter-spacing: -0.015em; color: var(--ink); max-width: 24ch; }
        .landing .l-quote-cite { font-size: 17px; color: var(--muted); max-width: 52ch; }
        .landing .l-motif { position: absolute; right: 0; bottom: 0; width: 320px; height: 220px; pointer-events: none; opacity: 0.9;
            background: linear-gradient(315deg, var(--teal-600) 0 26%, transparent 26.5%), linear-gradient(315deg, var(--red-600) 0 34%, transparent 34.5%); }
        .landing .l-motif-left { right: auto; left: 0; transform: scaleX(-1); opacity: 0.35; }

        /* ── Certificate band ─────────────────────────────────────────────── */
        .landing .l-certband { position: relative; overflow: hidden; padding: 80px 0; background: linear-gradient(135deg, var(--teal-900), var(--teal-800)); color: #fff; }
        .landing .l-certband-grid { position: relative; display: grid; grid-template-columns: 5fr 6fr; gap: 80px; align-items: center; }
        .landing .l-certband .l-stagger { display: flex; flex-direction: column; gap: 20px; }
        /* Completion timeline: rows tick in one after another, then the certificate row glows. */
        .landing .l-tl { position: relative; padding: 28px 30px 26px; background: var(--card); border-radius: var(--radius); color: var(--ink); box-shadow: var(--shadow-lg); }
        .landing .l-tl-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding-bottom: 18px; border-bottom: 1px solid var(--line); }
        .landing .l-tl-status { display: inline-flex; align-items: center; gap: 8px; font-family: var(--font-mono); font-size: 11px; letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted); }
        .landing .l-tl-status-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--teal-600); animation: l-pulse-teal 2s ease-out infinite; }
        .landing .l-tl-list { position: relative; margin: 0; padding: 18px 0 4px; list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .landing .l-tl-list::before { content: ""; position: absolute; left: 15px; top: 30px; bottom: 30px; width: 2px; background: var(--line); }
        .landing .l-tl-list::after { content: ""; position: absolute; left: 15px; top: 30px; bottom: 30px; width: 2px; background: var(--teal-600); transform: scaleY(0); transform-origin: top; }
        .landing.has-reveal .is-in .l-tl-list::after { animation: l-sweep-y 1800ms var(--ease) 400ms forwards; }
        .landing:not(.has-reveal) .l-tl-list::after { transform: none; }
        .landing .l-tl-item { position: relative; display: grid; grid-template-columns: 32px 1fr auto; gap: 16px; align-items: center; padding: 10px 12px 10px 0; border-radius: var(--radius-sm); }
        .landing .l-tl-mark { position: relative; z-index: 1; display: inline-flex; width: 32px; height: 32px; border-radius: 50%; align-items: center; justify-content: center; background: var(--teal-600); color: #fff; border: 3px solid var(--card); box-shadow: 0 0 0 2px var(--teal-100); }
        .landing .l-tl-mark-amber { background: var(--amber); color: var(--amber-ink); box-shadow: 0 0 0 2px rgba(246, 183, 60, 0.4); }
        .landing .l-tl-text { display: flex; flex-direction: column; gap: 2px; font-size: 13px; color: var(--muted); }
        .landing .l-tl-text strong { font-size: 15px; font-weight: 600; color: var(--ink); }
        .landing .l-tl-meta { font-family: var(--font-mono); font-size: 12px; color: var(--teal-700); padding: 4px 10px; border-radius: 999px; background: var(--teal-50); }
        .landing .l-tl-meta-amber { color: var(--amber-ink); background: rgba(246, 183, 60, 0.22); }
        .landing .l-tl-item.is-issue { background: linear-gradient(90deg, rgba(246, 183, 60, 0.14), transparent 70%); }
        .landing.has-reveal .l-tl-item { opacity: 0; transform: translateX(-8px); }
        .landing.has-reveal .is-in .l-tl-item { animation: l-slide 520ms var(--ease) forwards; animation-delay: calc(500ms + var(--d, 0) * 420ms); }
        .landing.has-reveal .is-in .l-tl-item.is-issue .l-tl-mark { animation: l-pop 600ms var(--ease) calc(500ms + 3 * 420ms + 300ms) both; }
        .landing .l-tl-foot { margin-top: 14px; padding-top: 16px; border-top: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .landing .l-tl-link { display: inline-flex; align-items: center; gap: 8px; font-family: var(--font-mono); font-size: 12px; color: var(--muted); }
        .landing .l-tl-link svg { color: var(--teal-600); }
        .landing .l-tl-seal { display: inline-flex; align-items: center; gap: 8px; padding: 7px 12px; border-radius: 999px; background: var(--teal-600); color: #fff; font-size: 12px; font-weight: 600; }
        @keyframes l-slide      { to { opacity: 1; transform: none; } }
        @keyframes l-sweep-y    { to { transform: scaleY(1); } }
        @keyframes l-pop        { 0% { transform: scale(1); } 50% { transform: scale(1.25); box-shadow: 0 0 0 10px rgba(246, 183, 60, 0.25); } 100% { transform: scale(1); } }
        @keyframes l-pulse-teal { 0% { box-shadow: 0 0 0 0 rgba(0, 97, 92, 0.45); } 100% { box-shadow: 0 0 0 9px rgba(0, 97, 92, 0); } }

        /* ── FAQ ──────────────────────────────────────────────────────────── */
        .landing .l-faq { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 24px; max-width: 1040px; margin: 0 auto; }
        .landing .l-faq-item { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius-sm); padding: 0 22px; transition: border-color 200ms, box-shadow 200ms; }
        .landing .l-faq-item:hover, .landing .l-faq-item[open] { border-color: var(--teal-200); box-shadow: 0 10px 30px rgba(26, 24, 21, 0.06); }
        .landing .l-faq-item summary { list-style: none; cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 20px 0; font-size: 17px; font-weight: 600; color: var(--ink); }
        .landing .l-faq-item summary::-webkit-details-marker { display: none; }
        .landing .l-faq-item[open] summary { color: var(--teal-700); }
        .landing .l-faq-plus { position: relative; flex: 0 0 auto; width: 28px; height: 28px; border-radius: 50%; background: var(--teal-50); transition: transform 240ms var(--ease), background 200ms; }
        .landing .l-faq-plus::before, .landing .l-faq-plus::after { content: ""; position: absolute; left: 50%; top: 50%; width: 12px; height: 2px; background: var(--teal-600); transform: translate(-50%, -50%); border-radius: 2px; }
        .landing .l-faq-plus::after { transform: translate(-50%, -50%) rotate(90deg); }
        .landing .l-faq-item[open] .l-faq-plus { transform: rotate(45deg); background: var(--teal-600); }
        .landing .l-faq-item[open] .l-faq-plus::before, .landing .l-faq-item[open] .l-faq-plus::after { background: #fff; }
        .landing .l-faq-item p { padding: 0 0 22px; font-size: 15px; line-height: 1.65; color: var(--muted); animation: l-rise 260ms var(--ease); }

        /* ── Closing ──────────────────────────────────────────────────────── */
        .landing .l-closing { position: relative; overflow: hidden; padding: 88px 0; background: linear-gradient(135deg, var(--teal-700), var(--teal-900)); color: #fff; }
        .landing .l-closing-inner { position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 24px; }
        .landing .l-closing .l-sub { text-align: center; }

        /* ── Footer ───────────────────────────────────────────────────────── */
        .landing .l-footer { border-top: 1px solid var(--line); background: var(--card); }
        .landing .l-footer-inner { padding: 48px 24px; display: grid; grid-template-columns: 1.4fr 1fr auto; gap: 32px; align-items: center; }
        .landing .l-footer-brand { display: flex; flex-direction: column; gap: 12px; }
        .landing .l-footer-brand img { height: 30px; width: auto; mix-blend-mode: multiply; align-self: flex-start; }
        .landing .l-footer-brand p { font-size: 14px; color: var(--muted); max-width: 40ch; }
        .landing .l-footer-links { display: flex; flex-wrap: wrap; gap: 10px 24px; font-size: 14px; font-weight: 500; color: var(--body); }
        .landing .l-footer-links a:hover { color: var(--teal-700); }
        .landing .l-footer-copy { font-size: 13px; color: var(--subtle); }

        /* ── Motion ───────────────────────────────────────────────────────── */
        .landing.has-reveal .l-reveal { opacity: 0; transform: translateY(10px); transition: opacity 420ms var(--ease), transform 420ms var(--ease); }
        .landing.has-reveal .l-reveal.is-in { opacity: 1; transform: none; }
        .landing.has-reveal .l-reveal-delay { transition-delay: 140ms; }
        .landing.has-reveal .l-stagger > * { opacity: 0; transform: translateY(12px); }
        .landing.has-reveal .is-in .l-stagger > *, .landing.has-reveal .is-in.l-stagger > * { animation: l-rise 560ms var(--ease) forwards; }
        .landing.has-reveal .is-in .l-stagger > :nth-child(1), .landing.has-reveal .is-in.l-stagger > :nth-child(1) { animation-delay: 60ms; }
        .landing.has-reveal .is-in .l-stagger > :nth-child(2), .landing.has-reveal .is-in.l-stagger > :nth-child(2) { animation-delay: 170ms; }
        .landing.has-reveal .is-in .l-stagger > :nth-child(3), .landing.has-reveal .is-in.l-stagger > :nth-child(3) { animation-delay: 280ms; }
        .landing.has-reveal .is-in .l-stagger > :nth-child(4), .landing.has-reveal .is-in.l-stagger > :nth-child(4) { animation-delay: 390ms; }
        .landing.has-reveal .is-in .l-stagger > :nth-child(5), .landing.has-reveal .is-in.l-stagger > :nth-child(5) { animation-delay: 500ms; }
        .landing.has-reveal .l-stagger-item { opacity: 0; transform: translateY(12px); }
        .landing.has-reveal .is-in .l-stagger-item { animation: l-rise 560ms var(--ease) forwards; animation-delay: calc(80ms + var(--i, 0) * 90ms); }

        @keyframes l-rise     { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
        @keyframes l-drift    { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
        @keyframes l-drift-xy { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(30px, 20px) scale(1.06); } }
        @keyframes l-pulse    { 0% { box-shadow: 0 0 0 0 rgba(246, 183, 60, 0.55); } 100% { box-shadow: 0 0 0 10px rgba(246, 183, 60, 0); } }
        @keyframes l-ring     { to { stroke-dashoffset: 48.4; } }
        @keyframes l-bar      { to { transform: scaleX(1); } }
        @keyframes l-sweep    { to { transform: scaleX(1); } }

        @media (prefers-reduced-motion: reduce) {
            .landing .l-blob, .landing .l-float, .landing .l-pill-dot { animation: none !important; }
            .landing .l-photo { transform: none; }
            .landing .l-tl-status-dot { animation: none; }
            .landing .l-showcase-panel:hover .l-device-screen img { transform: none; }
            .landing .l-journey::after { transform: none; animation: none; }
        }

        /* Desktop scale: every section fits one laptop screen. Header, hero and all
           sections scale together; phones and tablets are untouched. */
        @media (min-width: 1100px) { .landing { zoom: 0.84; } }

        /* ── Responsive ───────────────────────────────────────────────────── */
        @media (max-width: 1100px) {
            .landing .l-hero-h1 { font-size: 60px; }
        }
        @media (max-width: 900px) {
            .landing .l-nav { display: none; }
            .landing .l-nav-link { display: none; }
            .landing .l-hero { padding: 140px 0 88px; }
            .landing .l-header-inner { height: 72px; }
            .landing .l-hero-grid { grid-template-columns: 1fr; gap: 48px; }
            .landing .l-hero-h1 { font-size: 46px; }
            .landing .l-photo { transform: none; }
            .landing .l-glass-progress { left: 12px; bottom: -20px; }
            .landing .l-glass-quiz { right: 12px; top: -16px; }
            .landing .l-glass-cert { display: none; }
            .landing .l-h2 { font-size: 38px; }
            .landing .l-h2-xl { font-size: 46px; }
            .landing .l-section-pad { padding: 72px 0; }
            .landing .l-journey { grid-template-columns: 1fr; gap: 40px; }
            .landing .l-journey::before, .landing .l-journey::after { display: none; }
            .landing .l-showcase { grid-template-columns: 1fr; gap: 28px; }
            .landing .l-showcase-tab.is-active { transform: none; }
            .landing .l-showcase-stage { min-height: 0; }
            .landing .l-showcase-panel .l-device { padding: 24px 0 0 24px; }
            .landing .l-showcase-panel .l-device-screen { height: 240px; }
            .landing .l-strip { grid-template-columns: 1fr; }
            .landing .l-certband-grid { grid-template-columns: 1fr; gap: 48px; }
            .landing .l-tl { padding: 22px 18px; }
            .landing .l-quote-text { font-size: 32px; }
            .landing .l-faq { grid-template-columns: 1fr; }
            .landing .l-footer-inner { grid-template-columns: 1fr; gap: 24px; }
        }
        @media (max-width: 640px) {
            .landing .l-hero-h1 { font-size: 38px; }
            .landing .l-hero-lead { font-size: 17px; }
            .landing .l-btn-lg { height: 50px; padding: 0 20px; font-size: 15px; }
            .landing .l-glass-progress { width: 250px; }
            .landing .l-motif { width: 180px; height: 120px; }
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
