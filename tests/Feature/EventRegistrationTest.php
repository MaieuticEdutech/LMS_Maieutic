<?php

declare(strict_types=1);

use App\Events\AttemptGraded;
use App\Events\CourseCompleted;
use App\Events\EnrollmentGranted;
use App\Events\EnrollmentRevoked;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Events\PasswordUpdatedViaController;

/*
|--------------------------------------------------------------------------
| Every event has EXACTLY ONE listener
|--------------------------------------------------------------------------
|
| Laravel 13 discovers listeners in app/Listeners by convention, and discovery
| is ON by default. AppServiceProvider also registers every listener
| explicitly — deliberately, because an explicit list is greppable and cannot
| silently stop being discovered.
|
| Both together means each listener is registered TWICE and runs twice per
| event. That was live in this codebase and produced:
|
|   - two "you now have access" emails per enrollment (Phase 11)
|   - ActivateUserAfterEmailVerification running twice per verification,
|     unnoticed since Phase 2 only because it happens to be idempotent
|
| AppServiceProvider::register() now disables discovery. This test is what
| stops that being undone by a future `withEvents()` call, a framework upgrade
| changing the default, or someone "helpfully" deleting the disable line.
|
| A doubled listener is close to invisible in normal use: nothing errors, and
| an idempotent listener hides it completely. It surfaces as a user receiving
| two identical emails — reported by the user, not by monitoring.
|
*/

it('registers exactly one listener per domain event', function (string $event): void {
    expect(Event::getListeners($event))->toHaveCount(1,
        "[{$event}] must have exactly one listener. Two usually means listener "
        .'auto-discovery has been re-enabled alongside the explicit registrations '
        .'in AppServiceProvider — every listener then runs twice.',
    );
})->with([
    Verified::class,
    JobFailed::class,
    PasswordReset::class,
    PasswordUpdatedViaController::class,
    EnrollmentGranted::class,
    EnrollmentRevoked::class,
    CourseCompleted::class,
]);

it('registers both AttemptGraded listeners, and only those two', function (): void {
    /*
     * The one event here that legitimately has more than one listener, so its
     * count is asserted separately rather than being left out of the guard
     * above — an uncovered event is exactly where a silent doubling would hide.
     *
     * Two, and they are deliberately separate:
     *   CompleteLessonOnPassedAttempt   — records progress (Phase 9)
     *   SendAssessmentResultNotification — emails the result (Phase 11)
     *
     * Merging them would mean a failing mail template could stop a lesson
     * being marked complete. Three would mean discovery is back on.
     */
    expect(Event::getListeners(AttemptGraded::class))->toHaveCount(2,
        '[AttemptGraded] must have exactly two listeners: progress and mail. '
        .'More usually means listener auto-discovery has been re-enabled.',
    );
});

it('registers exactly one listener for each mail transport event', function (string $event): void {
    /*
     * These two carry the FR-MAIL-10 email log. Doubling them would write two
     * `email_logs` rows per send, which would make the support-facing log
     * quietly untrustworthy — the one thing it exists not to be.
     */
    expect(Event::getListeners($event))->toHaveCount(1);
})->with([
    MessageSending::class,
    MessageSent::class,
]);

it('keeps listener auto-discovery disabled', function (): void {
    // The setting behind the assertions above, checked directly so a failure
    // points at the cause rather than the symptom.
    $provider = new ReflectionClass(Illuminate\Foundation\Support\Providers\EventServiceProvider::class);

    expect($provider->getStaticPropertyValue('shouldDiscoverEvents'))->toBeFalse();
});
