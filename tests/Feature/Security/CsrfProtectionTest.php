<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CSRF protection — Phase 14, §18.2 "CSRF absence rejection on
| state-changing routes"
|--------------------------------------------------------------------------
|
| Laravel 13's CSRF middleware — Illuminate\Foundation\Http\Middleware\
| PreventRequestForgery, wired into the 'web' group by the framework
| itself — auto-bypasses verification whenever
| `$this->app->runningInConsole() && $this->app->runningUnitTests()` is
| true, which is ALWAYS true for a Pest feature test. That's why a plain
| `$this->post(...)` in every other test file in this suite succeeds
| without a token: it's not proof CSRF is off, it's the test harness
| being convenient.
|
| So this file does not test through HTTP at all — it exercises the
| middleware class directly, the same way the framework's own test suite
| would, which is the only way to observe its real behaviour rather than
| its test-mode behaviour. What IS this app's job to verify: that nothing
| in app code carves an exception out of it (confirmed by a grep finding
| zero `PreventRequestForgery::except(...)` calls anywhere in app/), and
| that the one deliberate bypass — webhooks — is deliberate, documented,
| and replaced by an equivalent control (signature verification), not an
| accidental hole.
|
*/

use App\Http\Controllers\Webhooks\ProcessRazorpayWebhookController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

/**
 * The one piece of PreventRequestForgery this file cannot exercise as-is:
 * `runningUnitTests()` short-circuits verification whenever
 * `$this->app->runningInConsole() && $this->app->runningUnitTests()`,
 * which is unconditionally true inside a Pest run — there is no
 * app-level flag to flip that off from a test without lying to the whole
 * framework about its own environment.
 *
 * An anonymous subclass overriding just that one protected method is the
 * narrowest way to observe the middleware's real decision logic (origin
 * check, token comparison) instead of its test-mode escape hatch — it
 * changes nothing else about the class under test.
 */
function csrfMiddlewareWithoutTestBypass(): PreventRequestForgery
{
    return new class(app(), app('encrypter')) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    };
}

it('rejects a POST with no CSRF token and no valid origin', function (): void {
    $middleware = csrfMiddlewareWithoutTestBypass();

    $request = Request::create('/admin/students', 'POST', ['name' => 'forged']);
    $request->setLaravelSession(app('session.store'));
    // No Sec-Fetch-Site header (so origin verification can't pass it) and
    // no _token field — the exact shape of a forged cross-site request.

    expect(fn () => $middleware->handle($request, fn ($req) => response('should never run')))
        ->toThrow(TokenMismatchException::class);
});

it('accepts a POST whose token matches the session', function (): void {
    $middleware = csrfMiddlewareWithoutTestBypass();

    $session = app('session.store');
    $session->start();
    $token = $session->token();

    $request = Request::create('/admin/students', 'POST', ['_token' => $token]);
    $request->setLaravelSession($session);

    $response = $middleware->handle($request, fn ($req) => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('keeps the webhook route outside the web middleware group', function (): void {
    // The deliberate exception, and the only one: a payment gateway
    // cannot present a CSRF token, so authenticity comes from HMAC
    // signature verification instead (routes/webhooks.php's own
    // docblock). Registering this route under 'web' by accident would
    // silently make every webhook delivery fail closed, which is safe —
    // but the inverse mistake, a route meant to be CSRF-protected
    // registered WITHOUT 'web', would be a real hole. This proves the
    // one intentional exception stays exactly where it's meant to be.
    $route = collect(Route::getRoutes()->getRoutes())->first(
        fn ($r) => $r->getActionName() === ProcessRazorpayWebhookController::class.'@__invoke'
            || $r->getAction('controller') === ProcessRazorpayWebhookController::class,
    );

    expect($route)->not->toBeNull();

    if ($route === null) {
        return;
    }

    expect($route->gatherMiddleware())->not->toContain('web');
});
