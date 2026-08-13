<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\EnrollmentGranted;
use App\Models\Enrollment;
use App\Notifications\EnrollmentGrantedNotification;

/**
 * Emails a student when they gain access to a course (FR-MAIL-07).
 *
 * Attaches to Track A's `EnrollmentGranted` event rather than living inside
 * `GrantEnrollment`. That action is single-owner (Rule 3) and is the only
 * writer of enrollments; keeping mail out of it means the access path and the
 * notification path fail independently — a broken mail template can never
 * prevent a paid student from being enrolled.
 *
 * NOT ShouldQueue, deliberately, even though everything it does is queued.
 * The notification itself is the queued unit. Queueing this listener as well
 * would add a job whose only work is to dispatch another job, and would
 * serialise the event's Enrollment model a second time for no benefit.
 *
 * The event already fires only on a REAL grant — a repeated webhook returns
 * the existing enrollment without dispatching — so idempotency is handled
 * upstream and one grant produces exactly one email.
 */
final class SendEnrollmentGrantedNotification
{
    public function handle(EnrollmentGranted $event): void
    {
        $enrollment = $event->enrollment;

        /*
         * Loaded explicitly rather than relied on lazily. Model::preventLazyLoading
         * is active outside production, so a lazy read here would throw on a
         * queue worker — the one place nobody is watching. The event carries a
         * bare Enrollment, so these are its responsibility to resolve.
         */
        $enrollment->loadMissing(['user', 'course']);

        $student = $enrollment->user;
        $course = $enrollment->course;

        if ($student === null || $course === null) {
            // The enrollment outlived one of its parents — nothing to notify,
            // and inventing a recipient would be worse than staying silent.
            return;
        }

        $student->notify(new EnrollmentGrantedNotification(
            courseTitle: $course->title,
            wasReactivated: $event->wasReactivated,
            expiresAt: $enrollment->expires_at,
        ));
    }
}
