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
 * Lift a suspension (FR-ENR-09).
 *
 * The exact inverse of `SuspendEnrollment`, and nothing more. It restores the
 * status the enrollment held before the hold — `completed` if the student had
 * finished, `active` otherwise — so reinstating someone does not silently
 * demote a completed course back to in-progress.
 *
 * SUSPENDED ONLY, deliberately. This will not resurrect a revoked, refunded or
 * expired enrollment, because those did not lose access to a temporary hold:
 * they ended, in one case with money moving. Bringing one of those back is a
 * new grant, and a new grant goes through `GrantEnrollment` — the single
 * writer — where it is audited as such and can carry a fresh order and expiry.
 *
 * Allowing this action to revive them would put a second door next to the one
 * ADR-006 exists to keep single.
 */
final class ReinstateEnrollment
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EnrollmentAccessService $access,
    ) {}

    /**
     * @throws InvalidArgumentException when the enrollment is not suspended.
     */
    public function handle(Enrollment $enrollment, User $actor, ?string $note = null): Enrollment
    {
        if ($enrollment->status !== EnrollmentStatus::Suspended) {
            throw new InvalidArgumentException(sprintf(
                'Only a suspended enrollment can be reinstated. This one is %s — '
                .'restoring it is a new grant, which goes through GrantEnrollment.',
                $enrollment->status->value,
            ));
        }

        // A student who had finished the course is still finished.
        $restored = $enrollment->completed_at !== null
            ? EnrollmentStatus::Completed
            : EnrollmentStatus::Active;

        DB::transaction(function () use ($enrollment, $restored): void {
            $enrollment->forceFill(['status' => $restored])->save();
        });

        $this->access->flush();

        $this->audit->record(
            action: 'enrollment.reinstated',
            actor: $actor,
            subject: $enrollment,
            changes: [
                'before' => ['status' => EnrollmentStatus::Suspended->value],
                'after' => ['status' => $restored->value],
            ],
            description: sprintf(
                'Reinstated enrollment #%d%s.',
                $enrollment->getKey(),
                $note !== null && trim($note) !== '' ? ' — '.trim($note) : '',
            ),
        );

        return $enrollment;
    }
}
