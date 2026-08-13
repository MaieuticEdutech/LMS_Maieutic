<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\ActivateUserAfterEmailVerification;
use App\Listeners\AlertOnFailedJob;
use App\Listeners\LogOutboundEmail;
use App\Listeners\SendPasswordChangedNotification;
use App\Models\AssessmentAttempt;
use App\Policies\AttemptPolicy;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Events\PasswordUpdatedViaController;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureModels();
        $this->configureDates();
        $this->configureEvents();
        $this->configureAuthorization();
    }

    /**
     * Register domain event listeners explicitly.
     *
     * Preferred over relying on auto-discovery: an explicit list makes the
     * wiring greppable, and a listener that silently stops being discovered
     * would be a security-relevant failure here — the Verified listener is
     * what promotes a verified account to `active` (FR-AUTH-11).
     */
    private function configureEvents(): void
    {
        Event::listen(Verified::class, ActivateUserAfterEmailVerification::class);

        /*
         * Phase 11 — every outbound email is logged (FR-MAIL-10).
         *
         * Bound to the transport events rather than to individual mailables so
         * coverage cannot be forgotten when a later phase adds an email. See
         * LogOutboundEmail for why it is not itself queued.
         */
        Event::listen(MessageSending::class, [LogOutboundEmail::class, 'sending']);
        Event::listen(MessageSent::class, [LogOutboundEmail::class, 'sent']);

        /*
         * A job that has exhausted its retries is an operational incident, not
         * a log line to be discovered later (phases.md Phase 11: "failures
         * alert"). For mail specifically it means a student did not receive
         * something the system promised them.
         */
        Event::listen(JobFailed::class, AlertOnFailedJob::class);

        /*
         * Password changes are a security event the user must be told about
         * (FR-MAIL-07). Listening to events keeps the notification out of
         * ChangeUserPassword, which is Track A's action.
         *
         * BOTH events are required, and this is easy to get wrong: Fortify
         * dispatches `PasswordReset` from the forgot-password flow but
         * `PasswordUpdatedViaController` from the profile screen. Listening to
         * only the first would mean the notice never reaches the user whose
         * password was changed while they were signed in — precisely the case
         * where a hijacked session makes the warning worth sending.
         */
        Event::listen(PasswordReset::class, SendPasswordChangedNotification::class);
        Event::listen(PasswordUpdatedViaController::class, SendPasswordChangedNotification::class);
    }

    /**
     * Apply strict Eloquent behaviour outside production.
     *
     * These guards fail loudly in development and CI so that mistakes are
     * caught by a developer rather than by a user. They are disabled in
     * production, where a lazy-loaded relationship should degrade to a slow
     * page rather than a 500 (NFR-PERF-03).
     */
    private function configureModels(): void
    {
        $strict = ! $this->app->isProduction();

        /*
         * preventLazyLoading:
         * Throws when a relationship is lazily loaded, turning silent N+1
         * queries into immediate failures. This is the single most effective
         * defence against the performance problem this application is most
         * prone to: curriculum sidebars, admin tables and progress dashboards
         * all iterate collections of related models (NFR-PERF-03, AC-28).
         *
         * preventSilentlyDiscardingAttributes:
         * Throws when fill() receives an attribute that is not in $fillable,
         * instead of quietly dropping it. Without this, a mass-assignment
         * mistake looks like a feature that "just doesn't save" rather than an
         * error — and on models carrying `role`, `status` or `price_amount`
         * that ambiguity is a security problem, not a convenience one
         * (NFR-SEC-07).
         *
         * preventAccessingMissingAttributes:
         * Throws when reading an attribute the model did not load, catching
         * typos and partially-selected models at development time.
         */
        Model::preventLazyLoading($strict);
        Model::preventSilentlyDiscardingAttributes($strict);
        Model::preventAccessingMissingAttributes($strict);

        /*
         * Models are never unguarded. $fillable is declared explicitly on every
         * model (planning.md §9 rule 3); Model::unguard() would defeat that and
         * is prohibited.
         */
        Model::shouldBeStrict($strict);
    }

    /**
     * Use CarbonImmutable everywhere.
     *
     * Mutable date objects are a recurring source of subtle bugs: passing a
     * date into a method that calls ->addDays() silently mutates the caller's
     * value. Progress timestamps, attempt deadlines and enrollment expiry all
     * depend on dates being trustworthy after they are handed around
     * (FR-ASMT-10, FR-ENR-10).
     */
    private function configureDates(): void
    {
        Date::use(\Carbon\CarbonImmutable::class);
    }

    /**
     * Explicit policy registration where Laravel's naming-convention
     * auto-discovery does not apply.
     *
     * Every other policy in this codebase follows the convention
     * ({Model}Policy) and needs no entry here — see
     * tests/Feature/Catalogue/PolicyRegistrationTest.php for why that
     * matters: auto-discovery fails SILENTLY, so an unregistered policy
     * denies everything with no error anywhere.
     *
     * `AssessmentAttempt` → `AttemptPolicy` is the one mismatch: convention
     * would look for `AssessmentAttemptPolicy`, but architecture.md §8.3 and
     * the track brief both name the class `AttemptPolicy` — shorter, and
     * consistent with how every document in this project already refers to
     * it — so the mapping is registered explicitly here rather than renaming
     * the policy to satisfy a convention nobody else follows.
     */
    private function configureAuthorization(): void
    {
        Gate::policy(AssessmentAttempt::class, AttemptPolicy::class);
    }
}
