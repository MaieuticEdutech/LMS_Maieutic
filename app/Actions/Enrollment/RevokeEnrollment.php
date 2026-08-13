<?php

declare(strict_types=1);

namespace App\Actions\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Events\EnrollmentRevoked;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Enrollment\EnrollmentAccessService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Withdraw a student's access to a course (FR-ENR-08, AC-13).
 *
 * TERMINAL, and distinct from suspension. Revoking records who did it, when,
 * and why, and the enrollment does not come back on its own — only a fresh
 * `GrantEnrollment` can restore it. Use `SuspendEnrollment` for a pause.
 *
 * A REASON IS MANDATORY, enforced here rather than left to a form request.
 * Revocation is the action most likely to be questioned months later — by the
 * student who lost access, by support, or by whoever handles a refund dispute
 * — and "we do not know why" is not an answer. A validation rule in one
 * controller would leave every other caller free to skip it; this cannot be
 * skipped.
 *
 * ACCESS IS GONE THE MOMENT THIS COMMITS. The memo inside
 * EnrollmentAccessService is request-scoped, so nothing survives to serve a
 * stale `true` on the next request. It is flushed here anyway, because the
 * same request that revokes may go on to render a page (FR-ENR-08 requires
 * revocation to be immediate, not eventually-consistent).
 */
final class RevokeEnrollment
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EnrollmentAccessService $access,
    ) {}

    /**
     * @param  string  $reason  Mandatory. Recorded on the row and in the audit log.
     * @param  bool  $refunded  True when revocation follows a refund — the status
     *                          becomes `refunded` rather than `expired`, so the
     *                          commercial history stays legible.
     *
     * @throws InvalidArgumentException when no reason is given.
     */
    public function handle(
        Enrollment $enrollment,
        User $actor,
        string $reason,
        bool $refunded = false,
    ): Enrollment {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException(
                'A reason is required to revoke an enrollment. It is recorded against the '
                .'student\'s record and shown to whoever reviews this later.',
            );
        }

        $status = $refunded ? EnrollmentStatus::Refunded : EnrollmentStatus::Expired;
        $previous = $enrollment->status;

        DB::transaction(function () use ($enrollment, $actor, $reason, $status): void {
            $enrollment->forceFill([
                'status' => $status,
                'revoked_at' => now(),
                'revoked_by' => $actor->getKey(),
                'revoke_reason' => $reason,
            ])->save();
        });

        $this->access->flush();

        $this->audit->record(
            action: 'enrollment.revoked',
            actor: $actor,
            subject: $enrollment,
            changes: [
                'before' => ['status' => $previous->value],
                'after' => ['status' => $status->value, 'reason' => $reason],
            ],
            description: sprintf(
                'Revoked enrollment #%d — %s.',
                $enrollment->getKey(),
                $reason,
            ),
        );

        // Dispatched after the row is already dead. A listener that fails
        // cannot leave a revoked student holding access, because no listener
        // is what took it away.
        EnrollmentRevoked::dispatch($enrollment->refresh(), $reason, false);

        return $enrollment;
    }
}
