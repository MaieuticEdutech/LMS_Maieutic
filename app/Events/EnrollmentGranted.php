<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Enrollment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A student has been granted access to a course (FR-ENR-05).
 *
 * Emitted by `GrantEnrollment` and by nothing else — because that action is
 * the only writer of enrollments (ADR-006), this event is a complete and
 * trustworthy record of every access grant in the system.
 *
 * IT FIRES ONLY ON A REAL GRANT. A repeated call for a user who already holds
 * an active enrollment returns the existing row without dispatching, so a
 * webhook delivered three times sends one welcome email rather than three.
 * Idempotency that stopped at the database row would still spam the student.
 *
 * LISTENERS (Track C, Phase 11): welcome/enrollment mail, progress seeding,
 * cache warming. Track C owns `app/Listeners/**` and attaches to this event
 * without needing a change here — which is why the payload is explicit rather
 * than "the listener can fetch what it needs".
 *
 * The `$wasReactivated` flag distinguishes a first-time grant from a revoked
 * or expired enrollment being brought back. Both are grants; only one of them
 * should read as "welcome to the course".
 */
final class EnrollmentGranted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly bool $wasReactivated = false,
    ) {}
}
