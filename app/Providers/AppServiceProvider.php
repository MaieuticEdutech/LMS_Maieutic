<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\AttemptGraded;
use App\Events\CourseCompleted;
use App\Events\CourseStructureChanged;
use App\Events\EnrollmentGranted;
use App\Events\EnrollmentRevoked;
use App\Jobs\Progress\RecalculateProgressForCourseEnrollments;
use App\Listeners\ActivateUserAfterEmailVerification;
use App\Listeners\AlertOnFailedJob;
use App\Listeners\CompleteLessonOnPassedAttempt;
use App\Listeners\IssueCertificateOnCourseCompletion;
use App\Listeners\LogOutboundEmail;
use App\Listeners\SendAssessmentResultNotification;
use App\Listeners\SendCourseCompletedNotification;
use App\Listeners\SendEnrollmentGrantedNotification;
use App\Listeners\SendEnrollmentRevokedNotification;
use App\Listeners\SendPasswordChangedNotification;
use App\Models\AssessmentAttempt;
use App\Policies\AttemptPolicy;
use App\Policies\ReportPolicy;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;
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
        /*
         * Turn off convention-based listener discovery. See configureEvents()
         * for the full reasoning: with it on, every listener registered
         * explicitly there is ALSO found by convention and runs twice.
         *
         * Done here rather than via withEvents(false) in bootstrap/app.php
         * because that file belongs to Track A (CLAUDE.md, shared-files
         * table). This static call has the identical effect and stays inside a
         * file this track already maintains.
         *
         * It must happen in register(), before the framework's event provider
         * boots and performs discovery.
         */
        EventServiceProvider::disableEventDiscovery();
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
        $this->configureDevQueueWorker();
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
        /*
         * ═════════════════════════════════════════════════════════════════
         * AUTO-DISCOVERY IS OFF, AND THE EXPLICIT LIST BELOW IS THE ONLY
         * REGISTRATION. Turning it back on double-fires every listener here.
         *
         * Laravel 13 discovers listeners in app/Listeners by convention, and
         * discovery is enabled by default. Every class below therefore ALSO
         * matched by convention, so each was registered twice and its handle()
         * ran twice per event. Verified by counting registrations:
         * EnrollmentGranted, EnrollmentRevoked and Verified each reported two
         * listeners.
         *
         * The symptoms are not cosmetic:
         *   - two "you now have access" emails per enrollment (Phase 11)
         *   - ActivateUserAfterEmailVerification running twice per
         *     verification, which has been true since Phase 2 and was
         *     invisible only because that listener happens to be idempotent
         *
         * Discovery is disabled rather than deleting these calls, because the
         * docblock above is right: an explicit list is greppable, and a
         * listener that silently STOPS being discovered would be a
         * security-relevant failure — this is where a verified account is
         * promoted to `active` (FR-AUTH-11). Explicit registration cannot fail
         * that way. It just may not be doubled.
         *
         * tests/Feature/EventRegistrationTest.php asserts exactly one listener
         * per event, so this cannot silently regress.
         * ═════════════════════════════════════════════════════════════════
         */
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

        /*
         * Enrollment mail (FR-MAIL-07). Attached to Track A's events rather
         * than living inside GrantEnrollment / RevokeEnrollment, which are
         * single-owner (Rule 3): the access path and the notification path
         * must fail independently, so a broken template can never stop a paid
         * student being enrolled.
         */
        Event::listen(EnrollmentGranted::class, SendEnrollmentGrantedNotification::class);
        Event::listen(EnrollmentRevoked::class, SendEnrollmentRevokedNotification::class);

        /*
         * Progress (Phase 9). A curriculum change moves the denominator of
         * every enrollment's percentage in that course, so the batch job
         * refreshes them all (FR-PROG-09, AC-30).
         *
         * Registered explicitly like every other listener here: convention
         * discovery is off project-wide, so a listener not named here simply
         * never runs.
         */
        /*
         * A passed attempt completes the quiz lesson hosting it (FR-PROG-04).
         * Wired through the event rather than called inside GradingService --
         * that is Track B's, and grading must not fail because progress did.
         */
        Event::listen(AttemptGraded::class, CompleteLessonOnPassedAttempt::class);

        /*
         * Phase 11's last two transactional emails, unblocked by Phases 8 and 9
         * shipping their triggers (FR-MAIL-07).
         *
         * Two listeners now hang off AttemptGraded — one records progress, one
         * sends mail. They are separate on purpose: a failing template must not
         * be able to stop a lesson being marked complete, which it could if the
         * two shared a listener.
         */
        Event::listen(AttemptGraded::class, SendAssessmentResultNotification::class);
        Event::listen(CourseCompleted::class, SendCourseCompletedNotification::class);

        /*
         * Two listeners on CourseCompleted now, and separate for the same reason
         * as the pair above: a certificate that fails to issue must not stop the
         * completion email, and a failing template must not stop the award.
         */
        Event::listen(CourseCompleted::class, IssueCertificateOnCourseCompletion::class);

        Event::listen(
            CourseStructureChanged::class,
            static fn (CourseStructureChanged $event) => RecalculateProgressForCourseEnrollments::dispatch(
                $event->course->getKey(),
            ),
        );
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

        /*
         * Reports have no model of their own — a report is a query, not a
         * record — so ReportPolicy is registered as named abilities rather
         * than against a class. Authorising `viewOperational` on User::class
         * would resolve to UserPolicy and silently ask the wrong question.
         *
         * The rules still live in ReportPolicy (phases.md Phase 13) rather
         * than as closures here, so the financial boundary is stated in one
         * readable place (FR-RPT-07).
         */
        Gate::define('reports.view', [ReportPolicy::class, 'viewAny']);
        Gate::define('reports.operational', [ReportPolicy::class, 'viewOperational']);
        Gate::define('reports.financial', [ReportPolicy::class, 'viewFinancial']);
    }

    /**
     * Make `composer dev`'s worker drain EVERY queue, in priority order.
     *
     * ═════════════════════════════════════════════════════════════════════
     * THE DEFAULT DEV WORKER DRAINS ONE QUEUE OUT OF FOUR.
     *
     * Laravel's dev runner registers `queue:listen --tries=1 --timeout=0`
     * with no --queue argument, so it processes `default` and nothing else.
     * This application dispatches across four (config/lms.php): critical,
     * mail, default and low.
     *
     * The practical effect was that in local development EVERY OUTBOUND EMAIL
     * queued and never sent — activation links, enrolment confirmations,
     * assessment results — because they all land on `mail`. Nothing failed and
     * nothing appeared in failed_jobs; the rows simply accumulated, so the
     * symptom was a feature that looked built and did nothing. Certificates
     * happened to work only because issuing sits on `default`.
     *
     * The order comes from config('lms.queues.priority'), which that file
     * already calls the single source of truth and which Phase 16 will read to
     * build the supervisor's --queue argument. Reading it here too means local
     * and production drain in the same order rather than drifting apart.
     * ═════════════════════════════════════════════════════════════════════
     *
     * Console-only and inert in production: DevCommands no-ops unless the dev
     * runner is what is executing.
     */
    private function configureDevQueueWorker(): void
    {
        if (! $this->app->runningInConsole() || $this->app->isProduction()) {
            return;
        }

        /** @var list<string> $priority */
        $priority = config()->array('lms.queues.priority', ['default']);

        if ($priority === []) {
            return;
        }

        // The stock 'queue' entry is dropped rather than left alongside ours:
        // two listeners both polling `default` would process the same work
        // twice over and make the runner's output impossible to read.
        DevCommands::except('queue');

        DevCommands::artisan(
            sprintf('queue:listen --queue=%s --tries=1 --timeout=0', implode(',', $priority)),
            'queues',
        );
    }
}
