<?php

declare(strict_types=1);

namespace App\Actions\Enrollment;

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Events\EnrollmentGranted;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * THE ONLY CODE PATH THAT CREATES AN ENROLLMENT (ADR-006, FR-ENR-05).
 *
 * ─────────────────────────────────────────────────────────────────────────
 * SINGLE-OWNER COMPONENT — Track A (Govind). planning.md §21.3.
 * Do not edit from another track. Consume it; if it needs a change, ask.
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Three callers, one door: a signature-verified payment webhook (Phase 12),
 * the reconciliation job that catches webhooks that never arrived, and an
 * audited admin grant. Nothing else may INSERT into `enrollments` — not a
 * seeder, not a console command, not a controller in a hurry.
 *
 * That constraint is what makes the project's first rule testable rather than
 * hopeful. "A browser saying payment succeeded grants nothing" is only true if
 * there is exactly one place where granting happens, and it is this one.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * IDEMPOTENCY IS THE POINT, AND IT HAS TWO LAYERS.
 *
 * Razorpay retries webhooks. The reconciliation job re-checks payments the
 * webhook may have missed. An admin double-clicks. All three arrive here, and
 * all three must produce ONE enrollment.
 *
 *   Layer 1 — read the existing row and return it. Handles the ordinary case.
 *   Layer 2 — UNIQUE(user_id, course_id) at the database. Handles the case
 *             Layer 1 cannot: two requests passing the check simultaneously,
 *             both finding nothing, both inserting. One wins, the loser
 *             catches the violation and re-reads.
 *
 * Layer 1 alone is a race condition with a comment claiming otherwise. Under
 * concurrent webhook delivery — which is the normal case, not the exotic one —
 * it would produce duplicate enrollments and, once Phase 11 lands, duplicate
 * welcome emails.
 *
 * THE EVENT FIRES ONLY ON A REAL CHANGE. A repeat call for an already-active
 * enrollment returns silently. Idempotency that stopped at the row would still
 * email the student three times.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class GrantEnrollment
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Grant, or return the grant that already exists.
     *
     * @param  User  $student  Who gains access.
     * @param  Course  $course  What they gain access to.
     * @param  EnrollmentSource  $source  How it was earned — purchase, admin grant, import.
     * @param  User|null  $actor  Who performed it. Null for system paths (webhook, reconciliation).
     * @param  Order|null  $order  The paid order, for purchase grants.
     * @param  DateTimeInterface|null  $expiresAt  Null means access does not expire.
     * @param  string|null  $reason  Required for admin grants — see FR-ENR-06.
     */
    public function handle(
        User $student,
        Course $course,
        EnrollmentSource $source,
        ?User $actor = null,
        ?Order $order = null,
        ?DateTimeInterface $expiresAt = null,
        ?string $reason = null,
    ): Enrollment {
        // LAYER 1. The overwhelmingly common case: it already exists.
        $existing = $this->find($student, $course);

        if ($existing !== null) {
            return $this->reconcile($existing, $student, $course, $source, $actor, $order, $expiresAt, $reason);
        }

        try {
            [$enrollment, $created] = $this->create($student, $course, $source, $actor, $order, $expiresAt, $reason);
        } catch (QueryException $e) {
            // LAYER 2. Another request won the race between our SELECT and our
            // INSERT. The database refused the duplicate, which is exactly
            // what it is there for. Re-read and treat it as the ordinary
            // already-exists case.
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            $winner = $this->find($student, $course);

            if ($winner === null) {
                // A unique violation with no row behind it means the
                // constraint fired for a reason we have not understood.
                // Guessing here would be worse than failing loudly.
                throw $e;
            }

            return $this->reconcile($winner, $student, $course, $source, $actor, $order, $expiresAt, $reason);
        }

        if ($created) {
            EnrollmentGranted::dispatch($enrollment, false);
        }

        return $enrollment;
    }

    /**
     * Deliberately unscoped by status: a revoked or expired enrollment still
     * occupies the UNIQUE(user_id, course_id) slot, so "is there a row?" and
     * "does it grant access?" are different questions. Only the second one
     * belongs to EnrollmentAccessService.
     */
    private function find(User $student, Course $course): ?Enrollment
    {
        return Enrollment::query()
            ->where('user_id', $student->getKey())
            ->where('course_id', $course->getKey())
            ->first();
    }

    /**
     * A row exists. Either it already grants access — return it untouched —
     * or it is revoked/expired and this call is a reinstatement.
     */
    private function reconcile(
        Enrollment $enrollment,
        User $student,
        Course $course,
        EnrollmentSource $source,
        ?User $actor,
        ?Order $order,
        ?DateTimeInterface $expiresAt,
        ?string $reason,
    ): Enrollment {
        if ($this->alreadyGrantsAccess($enrollment)) {
            // Idempotent no-op. No write, no audit entry, and above all no
            // event — this is the retried webhook, and the student has already
            // been welcomed.
            return $enrollment;
        }

        // Dead enrollment coming back to life: refunded then re-purchased,
        // expired then renewed, revoked in error then restored.
        DB::transaction(function () use ($enrollment, $source, $order, $expiresAt): void {
            $enrollment->forceFill([
                'status' => EnrollmentStatus::Active,
                'source' => $source,
                'order_id' => $order?->getKey() ?? $enrollment->order_id,
                'enrolled_at' => now(),
                'expires_at' => $expiresAt,
                // The previous revocation is history, not current state.
                'revoked_at' => null,
                'revoked_by' => null,
                'revoke_reason' => null,
            ])->save();
        });

        $this->audit->record(
            action: 'enrollment.reactivated',
            actor: $actor,
            subject: $enrollment,
            changes: ['after' => ['status' => EnrollmentStatus::Active->value, 'source' => $source->value]],
            description: sprintf(
                'Reactivated %s\'s enrollment in "%s"%s.',
                $student->name,
                $course->title,
                $reason !== null ? ' — '.$reason : '',
            ),
        );

        EnrollmentGranted::dispatch($enrollment->refresh(), true);

        return $enrollment;
    }

    /**
     * @return array{0: Enrollment, 1: bool}
     */
    private function create(
        User $student,
        Course $course,
        EnrollmentSource $source,
        ?User $actor,
        ?Order $order,
        ?DateTimeInterface $expiresAt,
        ?string $reason,
    ): array {
        $enrollment = DB::transaction(function () use ($student, $course, $source, $actor, $order, $expiresAt): Enrollment {
            $enrollment = new Enrollment;

            // user_id, course_id, source, status and granted_by are NOT
            // fillable (NFR-SEC-07) — ownership and access state can never be
            // set by a request payload, only here, explicitly.
            $enrollment->forceFill([
                'user_id' => $student->getKey(),
                'course_id' => $course->getKey(),
                'order_id' => $order?->getKey(),
                'source' => $source,
                'status' => EnrollmentStatus::Active,
                'enrolled_at' => now(),
                'expires_at' => $expiresAt,
                'granted_by' => $actor?->getKey(),
                'progress_percentage' => 0,
            ])->save();

            return $enrollment;
        });

        $this->audit->record(
            action: 'enrollment.granted',
            actor: $actor,
            subject: $enrollment,
            changes: ['after' => [
                'user_id' => $student->getKey(),
                'course_id' => $course->getKey(),
                'source' => $source->value,
                'expires_at' => $expiresAt?->format(DateTimeInterface::ATOM),
            ]],
            description: sprintf(
                'Granted %s access to "%s" via %s%s.',
                $student->name,
                $course->title,
                $source->value,
                $reason !== null ? ' — '.$reason : '',
            ),
        );

        return [$enrollment, true];
    }

    /**
     * The same rule EnrollmentAccessService applies, asked of one known row.
     *
     * Not a second definition of access: that service answers "may this user
     * reach this course", resolving roles and assignment. This answers the
     * narrower "is this particular row live", which is all the grant path
     * needs to decide between a no-op and a reactivation.
     */
    private function alreadyGrantsAccess(Enrollment $enrollment): bool
    {
        if (! in_array($enrollment->status, [EnrollmentStatus::Active, EnrollmentStatus::Completed], true)) {
            return false;
        }

        return $enrollment->expires_at === null || $enrollment->expires_at->isFuture();
    }

    /**
     * PostgreSQL SQLSTATE 23505 — unique_violation.
     *
     * Matched on the SQLSTATE rather than the message so it survives a locale
     * change or a driver upgrade rewording the text.
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23505';
    }
}
