<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Payment\PaymentGateway;
use App\Services\Payment\FakeGateway;
use App\Services\Payment\RazorpayGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Binds {@see PaymentGateway} to its concrete implementation
 * (architecture.md §11.1).
 *
 * Testing always resolves {@see FakeGateway} — "no test ever calls
 * Razorpay" is enforced here, not merely stated, because a test that
 * forgets to swap the binding still gets the fake rather than silently
 * reaching the network. Every other environment resolves
 * {@see RazorpayGateway}; `config('lms.payments.gateway')` exists for a
 * second gateway to be added later (architecture.md §11.1's "one class plus
 * a config switch"), but V1 ships Razorpay only (constraint C-07-adjacent —
 * no other gateway is configured), so this is not yet a match statement
 * with nothing to match against.
 */
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function (): PaymentGateway {
            if ($this->app->environment('testing')) {
                return new FakeGateway;
            }

            return $this->app->make(RazorpayGateway::class);
        });
    }

    /**
     * `webhook` limiter — routes/webhooks.php requirement 6: "though the
     * signature is the real control." Keyed per IP rather than per user,
     * because the caller is Razorpay's infrastructure, not an authenticated
     * account; generous enough that a burst of legitimate retries during an
     * incident is never what throttles a merchant, tight enough to blunt a
     * flood of forged deliveries before each one reaches signature
     * verification.
     */
    public function boot(): void
    {
        RateLimiter::for('webhook', static function (Request $request): Limit {
            return Limit::perMinute(60)->by('webhook:ip:'.(string) $request->ip());
        });

        /*
         * `checkout` limiter — routes/web.php's checkout group. Unlike the
         * webhook route, nothing here is signature-verified, so this limiter
         * is the actual control against a script hammering the endpoint to
         * spam Order rows, not a formality alongside a stronger one.
         */
        RateLimiter::for('checkout', static function (Request $request): Limit {
            $user = $request->user();

            return $user !== null
                ? Limit::perMinute(10)->by('checkout:user:'.$user->getAuthIdentifier())
                : Limit::perMinute(5)->by('checkout:ip:'.(string) $request->ip());
        });
    }
}
