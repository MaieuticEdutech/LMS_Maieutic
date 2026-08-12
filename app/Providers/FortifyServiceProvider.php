<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

/**
 * Wires Fortify to the LMS (ADR-013, architecture.md §7.1.1).
 *
 * Four responsibilities:
 *   1. Bind every Fortify view to an LMS Blade view — no framework markup.
 *   2. Replace Fortify's credential check so ACCOUNT STATUS is asserted in the
 *      same step, before any session exists.
 *   3. Configure rate limiters.
 *   4. Route users to their role's home after login.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, \App\Http\Responses\LoginResponse::class);
    }

    public function boot(): void
    {
        $this->registerPipelineActions();
        $this->registerViews();
        $this->registerAuthentication();
        $this->registerRateLimiters();
    }

    /**
     * Bind Fortify's pipeline contracts to the LMS adapters (ADR-013).
     *
     * Each adapter validates input and delegates to a domain Action, so the
     * behaviour stays callable from a job, a console command or a test without
     * an HTTP request.
     *
     * `updateUserProfileInformationUsing` is deliberately NOT bound: that
     * feature is disabled (config/fortify.php), because changing the login
     * email must trigger re-verification — behaviour Fortify's generic
     * endpoint does not have.
     */
    private function registerPipelineActions(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
    }

    /**
     * Every Fortify screen renders an LMS view (C-06).
     *
     * Fortify ships no markup of its own, which is exactly why it was chosen:
     * the LMS owns the entire interface while Fortify owns the security
     * primitives underneath.
     */
    private function registerViews(): void
    {
        Fortify::loginView(static fn () => view('auth.login'));
        Fortify::registerView(static fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(static fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(static fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(static fn () => view('auth.verify-email'));
        Fortify::confirmPasswordView(static fn () => view('auth.confirm-password'));
    }

    /**
     * THE STATUS GATE — the most security-sensitive method in Phase 2.
     *
     * Fortify's default credential check verifies the password and nothing
     * else. Overriding it here means the password check and the
     * "may this account authenticate?" check happen TOGETHER, and a session is
     * established only if both pass.
     *
     * WHY NOT JUST USE MIDDLEWARE?
     * Middleware runs AFTER the guard has already logged the user in. A
     * suspended user would briefly hold a valid session, and any code path
     * that ran before the middleware — a listener, a redirect, a response
     * macro — would see them as authenticated. Asserting status here means
     * such a session is never created at all (architecture.md §7.1.1).
     * EnsureUserIsActive remains as defence in depth for sessions whose user
     * is deactivated mid-session.
     *
     * NO USER ENUMERATION (FR-AUTH-09): returning null produces one generic
     * "these credentials do not match our records" message for every failure —
     * unknown email, wrong password, suspended, or awaiting activation. An
     * attacker learns nothing about which accounts exist or their state.
     *
     * TIMING: Hash::check is run against a dummy hash when the user is not
     * found, so a missing account takes about as long as a wrong password and
     * response time does not leak account existence.
     */
    private function registerAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $audit = app(AuditLogger::class);

            $email = Str::lower(trim((string) $request->input(Fortify::username())));
            $password = (string) $request->input('password');

            $user = User::query()->where('email', $email)->first();

            // A pending-activation account has password = NULL. Hash::check
            // against null would be a type error, so the null case is handled
            // explicitly — and always fails.
            $passwordMatches = $user?->password !== null
                && Hash::check($password, $user->password);

            if (! $passwordMatches) {
                // Equalise timing for a non-existent account.
                if ($user === null) {
                    Hash::check($password, '$2y$12$'.str_repeat('0', 53));
                }

                $audit->recordSecurityEvent(
                    action: 'auth.login.failed',
                    description: "Failed login attempt for {$email}.",
                );

                return null;
            }

            if (! $user->canAuthenticate()) {
                $audit->recordSecurityEvent(
                    action: 'auth.login.blocked',
                    description: "Login blocked for {$email}: account status is {$user->status->value}.",
                );

                return null;
            }

            $user->forceFill(['last_login_at' => now()])->save();

            $audit->record(
                action: 'auth.login.succeeded',
                actor: $user,
                subject: $user,
                description: "Successful login for {$user->email}.",
            );

            return $user;
        });
    }

    /**
     * Rate limiters (architecture.md §18.3, NFR-SEC-10).
     *
     * The login limiter is keyed on email + IP TOGETHER, deliberately:
     *   - keyed on IP alone, one attacker behind a shared NAT could lock out
     *     every legitimate user on that address;
     *   - keyed on email alone, an attacker could lock any known user out of
     *     their own account from anywhere — a denial-of-service disguised as a
     *     security control.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', static function (Request $request): Limit {
            $throttleKey = Str::transliterate(
                Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip(),
            );

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('register', static fn (Request $request): Limit => Limit::perHour(5)->by((string) $request->ip()));

        // Covers both "forgot password" and "resend activation link". Keyed on
        // email so an attacker cannot use the endpoint to spam a victim's
        // inbox, nor to enumerate accounts by observing throttle behaviour.
        RateLimiter::for('password-reset', static function (Request $request): Limit {
            $key = Str::lower(trim((string) $request->input('email'))).'|'.$request->ip();

            return Limit::perHour(3)->by(Str::transliterate($key));
        });

        RateLimiter::for('activation-resend', static function (Request $request): Limit {
            $user = $request->user();
            $key = $user !== null ? 'user:'.$user->getKey() : 'ip:'.$request->ip();

            return Limit::perHour(3)->by($key);
        });
    }
}
