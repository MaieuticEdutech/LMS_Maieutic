<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\EnrollmentRevoked;
use App\Notifications\EnrollmentRevokedNotification;

/**
 * Emails a student when their access to a course ends (FR-MAIL-07).
 *
 * Covers all three routes to losing access — admin revocation, refund, and
 * scheduled expiry — because the event does, and a student does not care
 * which internal path removed their access.
 *
 * THIS LISTENER NEVER GATES ANYTHING. The event fires after the revocation has
 * already committed, so if this fails the student is still correctly locked
 * out; they simply were not told. That ordering is Track A's design and is the
 * reason a mail failure here is a support problem rather than a security one.
 *
 * See SendEnrollmentGrantedNotification for why this is not itself ShouldQueue.
 */
final class SendEnrollmentRevokedNotification
{
    public function handle(EnrollmentRevoked $event): void
    {
        $enrollment = $event->enrollment;

        // Explicit: Model::preventLazyLoading would otherwise throw on a worker.
        $enrollment->loadMissing(['user', 'course']);

        $student = $enrollment->user;
        $course = $enrollment->course;

        if ($student === null || $course === null) {
            return;
        }

        $student->notify(new EnrollmentRevokedNotification(
            courseTitle: $course->title,
            reason: $event->reason,
            wasAutomatic: $event->wasAutomatic,
        ));
    }
}
