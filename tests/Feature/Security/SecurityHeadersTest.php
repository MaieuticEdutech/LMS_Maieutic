<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Security headers — Phase 14, AC-35
|--------------------------------------------------------------------------
|
| Proves the headers exist on a real response rather than trusting the
| middleware class in isolation: a route not covered by the global
| middleware stack, or a header set on the wrong response object, would
| pass a unit test on SecurityHeaders alone and still ship unprotected.
|
*/

it('sends a tuned Content-Security-Policy header on a public page', function (): void {
    $response = $this->get('/');

    $response->assertOk();

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->not->toBeNull()
        ->and($csp)->toContain("default-src 'self'")
        ->and($csp)->toContain('https://checkout.razorpay.com')
        ->and($csp)->toContain("object-src 'none'")
        ->and($csp)->toContain("frame-ancestors 'self'");
});

it('sends a fresh nonce on every request', function (): void {
    $first = $this->get('/')->headers->get('Content-Security-Policy');
    $second = $this->get('/')->headers->get('Content-Security-Policy');

    preg_match("/'nonce-([^']+)'/", (string) $first, $firstMatch);
    preg_match("/'nonce-([^']+)'/", (string) $second, $secondMatch);

    expect($firstMatch[1] ?? null)->not->toBeNull()
        ->and($firstMatch[1] ?? null)->not->toBe($secondMatch[1] ?? null);
});

it('sends the rest of the security header suite', function (): void {
    $response = $this->get('/');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()');
});

it('sends security headers on a route outside the web middleware group', function (): void {
    // Webhooks intentionally skip the `web` group (no session, no CSRF —
    // see bootstrap/app.php) but must still get these headers, since
    // SecurityHeaders is appended to the global stack, not the web group.
    $response = $this->postJson('/webhooks/razorpay', []);

    expect($response->headers->get('Content-Security-Policy'))->not->toBeNull();
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('sends security headers on a 404', function (): void {
    $response = $this->get('/this-route-does-not-exist');

    $response->assertNotFound();
    expect($response->headers->get('Content-Security-Policy'))->not->toBeNull();
});
