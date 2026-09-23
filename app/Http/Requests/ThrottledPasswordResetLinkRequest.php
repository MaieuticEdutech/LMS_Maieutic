<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Http\Requests\SendPasswordResetLinkRequest;

/**
 * Rate-limits Fortify's own /forgot-password route (Phase 14 audit finding).
 *
 * Unlike its login route, Fortify's PasswordResetLinkController is not built
 * around a swappable action contract, and its route (`password.email`,
 * registered inside the vendor package's own routes.php) has no config hook
 * to attach a limiter the way `fortify.limiters.login` does for /login.
 * Before this class, `/forgot-password` had NO rate limiting whatsoever —
 * unlimited requests for any email address, a direct inbox-flooding vector
 * against a victim who never asked for a reset link.
 *
 * `authorize()` is the one method Laravel's FormRequest pipeline runs before
 * validation, which is what makes overriding it here — via a container bind
 * in FortifyServiceProvider — a real interception point without touching
 * vendor code or fighting Fortify's own route-registration timing.
 *
 * Keyed the same way as the `password-reset` limiter already defined in
 * FortifyServiceProvider (email + IP together): by email alone, an attacker
 * could lock a known victim out of ever requesting their own reset link —
 * itself a denial-of-service dressed up as a security control.
 */
class ThrottledPasswordResetLinkRequest extends SendPasswordResetLinkRequest
{
    public function authorize(): bool
    {
        $email = Str::lower(trim((string) $this->input('email')));
        $key = Str::transliterate($email.'|'.$this->ip());

        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages([
                'email' => 'Too many password reset requests. Please try again later.',
            ]);
        }

        RateLimiter::hit($key, 3600);

        return true;
    }
}
