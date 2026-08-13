<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Enrollment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A student's access to a course has been withdrawn (FR-ENR-08).
 *
 * Covers every route to losing access — an admin revoking manually, a refund,
 * or scheduled expiry — because a listener that needs to react to "this
 * student can no longer reach the course" should not have to enumerate the
 * reasons.
 *
 * ACCESS IS ALREADY GONE BY THE TIME THIS FIRES. The enrollment row is updated
 * inside the transaction and this event is dispatched afterCommit; no listener
 * is responsible for enforcing the revocation, and a failing listener cannot
 * leave a revoked student with access. Listeners notify and clean up. They
 * never gate.
 *
 * The reason is carried on the payload rather than left for the listener to
 * read back off the model, so a mail template can say why without a second
 * query and without racing a later status change.
 */
final class EnrollmentRevoked
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly string $reason,
        public readonly bool $wasAutomatic = false,
    ) {}
}
