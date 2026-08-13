<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks (Phase 11, architecture.md §13)
|--------------------------------------------------------------------------
|
| Driven by a single cron entry running `schedule:run` every minute; the
| entries below decide what actually happens (architecture.md §23).
|
| WHAT IS REGISTERED HERE AND WHAT IS NOT — read before adding to this file.
|
| architecture.md §13 lists eight recurring tasks. Most of them invoke
| commands owned by phases that are not built yet:
|
|   orders:reconcile        every 10m  Phase 12 (payments)
|   orders:cancel-abandoned hourly     Phase 12 (payments)
|   attempts:expire         every 5m   Phase 8  (assessment)
|   enrollments:expire      daily      Phase 6  — REGISTERED, see below
|   media:prune-orphans     daily      Phase 5  (media) — DeleteOrphanedMedia
|                                      exists as a job, but the command that
|                                      finds orphans to dispatch it for does not
|   backup:run              daily      Phase 16 (deployment)
|   audit:archive           monthly    Phase 13 (reporting)
|
| Those are deliberately NOT registered as stubs. A scheduled entry pointing at
| a command that does not exist fails silently every time the scheduler runs —
| it looks configured while doing nothing, which is worse than an obvious gap.
| Each phase registers its own task here when it builds its command; this
| comment is the checklist for doing so.
|
| Registered below: queue and mail hygiene (Phase 11), and enrollment expiry
| (Phase 6).
|
*/

/*
 * Failed jobs are kept long enough to investigate an incident and act on it,
 * then pruned so the table does not grow without bound. A week is chosen over
 * the framework's 24-hour default because a payment or enrollment failure
 * discovered on Monday is often reported by the student, not by monitoring,
 * and that report can easily arrive several days later (architecture.md §13).
 */
Schedule::command('queue:prune-failed --hours=168')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping();

/*
 * Batch records outlive the jobs they track and are only useful while a batch
 * is in flight or recently finished.
 */
Schedule::command('queue:prune-batches --hours=168')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping();

/*
 * Expired password-reset and activation tokens (FR-MAIL-03: links expire).
 * Flushing them means an intercepted-but-expired token cannot linger in the
 * table to be matched against.
 */
Schedule::command('auth:clear-resets')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping();

/*
 * Enrollment expiry — Phase 6, FR-ENR-10.
 *
 * THIS DOES NOT ENFORCE EXPIRY, IT RECORDS IT. EnrollmentAccessService
 * compares `expires_at` against now on every check, so access ends at the
 * timestamp whether or not this has run. That ordering is deliberate: if
 * access depended on a scheduled job, a stopped scheduler would silently
 * extend everyone's access, and the failure would be invisible — it would look
 * exactly like normal operation.
 *
 * What this provides is an accurate status column for reports and admin
 * filters, plus the expiry notification Phase 11's listeners attach to the
 * event. So a scheduler outage costs correct labelling and a late email. It
 * never costs access control.
 *
 * Daily, per architecture.md §13. Worth revisiting as a team: `expires_at` is
 * a timestamp rather than a date, so a daily sweep can leave the status column
 * up to 24 hours stale, and a student seeing "active" on access they lost that
 * morning is a support ticket. Hourly would cost 23 extra no-op runs a day.
 * Following the specification here rather than deviating on my own judgement —
 * architecture.md is a team-owned document.
 *
 * Idempotent: only `active` rows with a past `expires_at` are selected, so a
 * re-run after a failure is safe.
 */
Schedule::command('lms:enrollments:expire')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping();

/*
 * `onOneServer` on every entry above is not incidental. Production runs more
 * than one application server (architecture.md §22); without it each server
 * would run each task, and "prune" running concurrently on two machines is a
 * race with no benefit.
 */
