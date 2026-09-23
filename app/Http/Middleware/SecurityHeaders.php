<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security response headers, including a tuned Content-Security-Policy
 * (Phase 14, NFR-SEC-*, architecture.md §7.4).
 *
 * Applied globally (bootstrap/app.php), not just on the `web` group: AC-35
 * requires these headers on every response, webhooks included.
 *
 * WHY script-src NEEDS 'unsafe-eval'.
 * Livewire ships its own bundled Alpine.js build, not the `@alpinejs/csp`
 * build — every `x-data`/`x-on` expression across the admin and student
 * UI is evaluated through `new Function()`. Swapping in the CSP-safe Alpine
 * build would remove the need for this, but that is a frontend dependency
 * change (Track C's territory) with its own migration cost, not a Phase 14
 * task. Everything else in this policy is as strict as the app allows.
 *
 * WHY A PER-REQUEST NONCE INSTEAD OF 'unsafe-inline' FOR SCRIPTS.
 * The app has exactly three inline <script> blocks (Razorpay's checkout
 * handler, the certificate print button, and the certificate print-fit
 * script) and none of them can move to a bundled file without real cost —
 * Razorpay's needs live order data, the other two exist on a standalone
 * document page with no build pipeline. A nonce lets those three run while
 * refusing every other inline script an XSS payload might inject, which
 * 'unsafe-inline' would not.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));

        // Shared before $next() runs so every view rendered during this
        // request — including ones several layers deep in a Livewire
        // component — can read it as $cspNonce.
        View::share('cspNonce', $nonce);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), usb=(), payment=()');

        // frame-ancestors (CSP) is the modern control; X-Frame-Options stays
        // as a fallback for the handful of older browsers that ignore it.
        // SAMEORIGIN, not DENY — the lesson document player embeds its own
        // signed, same-origin media URL in an <iframe>, so the app frames
        // its own pages; nothing outside it ever should.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        $script = ["'self'", "'unsafe-eval'", "'nonce-{$nonce}'", 'https://checkout.razorpay.com'];
        $connect = ["'self'", 'https://api.razorpay.com', 'https://lumberjack.razorpay.com'];

        // Vite's dev server serves unbuilt assets from its own origin and
        // pushes HMR updates over a websocket. Neither exists once assets
        // are built for production (they become same-origin, covered by
        // 'self' already) — this carve-out must never reach a real
        // deployment, hence gated on the environment rather than a build
        // flag that could be forgotten.
        if (app()->environment('local')) {
            $script[] = 'http://localhost:5173';
            $connect[] = 'http://localhost:5173';
            $connect[] = 'ws://localhost:5173';
        }

        $directives = [
            "default-src 'self'",
            'script-src '.implode(' ', $script),
            // Tailwind/Blade components and the mail layout carry enough
            // inline style="" attributes (certificate document, mail
            // templates, dynamic progress bars) that nonce-per-style is not
            // practical; style injection alone cannot execute script, so
            // 'unsafe-inline' here is a deliberate, bounded trade-off.
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self'",
            "font-src 'self'",
            'connect-src '.implode(' ', $connect),
            // Razorpay's hosted checkout renders inside its own iframe.
            'frame-src https://checkout.razorpay.com',
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ];

        return implode('; ', $directives);
    }
}
