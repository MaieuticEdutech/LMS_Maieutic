<?php

declare(strict_types=1);

namespace App\Actions\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Enrollment\EnrollmentAccessService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pause a student's access without ending it (FR-ENR-09).
 *
 * The difference from revocation is intent, and it is worth keeping separate.
 * Suspension is reversible by design — a payment dispute under investigation,
 * a conduct issue, an administrative hold — and `ReinstateEnrollment` puts it
 * back exactly as it was. Revocation is the end of the relationship.
 *
 * Progress, completion state and last-lesson position are all left untouched.
 * A suspension that quietly reset someone's progress would turn a temporary
 * hold into permanent damage, and the student would have no way to tell what
 * had happened.
 *
 * `suspended` is denied by EnrollmentAccessService, so access stops the moment
 * this commits.
 *
 * No domain event is emitted. Phase 11's mail set has no suspension notice,
 * and inventing an event with no listener would be building ahead (Rule 5).
 * When the business asks for a suspension email, this is a two-line change.
 */
final class SuspendEnrollment
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EnrollmentAccessService $access,
    ) {}

    /**
     * @throws InvalidArgumentException when no reason is given, or when the
     *                                  enrollment is not currently suspendable.
     */
    public function handle(Enrollment $enrollment, User $actor, string $reason): Enrollment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('A reason is required to suspend an enrollment.');
        }

        // Suspending something already revoked, refunded or expired would
        // overwrite a terminal state with a reversible one, and the revocation
        // record would be lost. Refuse rather than quietly rewrite history.
        if (! in_array($enrollment->status, [EnrollmentStatus::Active, EnrollmentStatus::Completed], true)) {
            throw new InvalidArgumentException(sprintf(
                'Only an active or completed enrollment can be suspended. This one is %s.',
                $enrollment->status->value,
            ));
        }

        $previous = $enrollment->status;

        DB::transaction(function () use ($enrollment): void {
            $enrollment->forceFill(['status' => EnrollmentStatus::Suspended])->save();
        });

        $this->access->flush();

        $this->audit->record(
            action: 'enrollment.suspended',
            actor: $actor,
            subject: $enrollment,
            changes: [
                'before' => ['status' => $previous->value],
                'after' => ['status' => EnrollmentStatus::Suspended->value, 'reason' => $reason],
            ],
            description: sprintf('Suspended enrollment #%d — %s.', $enrollment->getKey(), $reason),
        );

        return $enrollment;
    }
}
