<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Contracts\Payment\PaymentGateway;
use App\Enums\EnrollmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\EnrollmentRevoked;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Audit\AuditLogger;
use App\Services\Enrollment\EnrollmentAccessService;
use App\Support\Payment\GatewayPayment;
use App\Support\Payment\GatewayRefund;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Apply a verified `refund.processed` webhook (FR-ENR-09, FR-PAY-09, AC-15).
 *
 * Refunds are started by a person in the Razorpay dashboard (assumption A-14);
 * the LMS only reacts to the signed webhook that results. This is the ONLY
 * place a refund changes local state.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * WHAT A REFUND DOES
 *
 *   PARTIAL  — the refunded amount is recorded on the payment and audited.
 *              Access is NOT touched: part of the money back is not a decision
 *              that the student should lose the course, and that call belongs
 *              to a person (the admin can revoke by hand).
 *   FULL     — the payment becomes `refunded`; if no other captured payment
 *              remains on the order the order becomes `refunded` too, and the
 *              enrollment that order bought moves to `refunded`, which stops
 *              granting access immediately (EnrollmentAccessService reads the
 *              status at request time — FR-ENR-07).
 *
 * IDEMPOTENT BY CONSTRUCTION, not only by the webhook's event_id dedupe. The
 * gateway reports the CUMULATIVE amount refunded on the payment, so this
 * writes an absolute figure, never "add this refund". A redelivery, a job
 * retry or two refund events arriving out of order all converge on the same
 * state, and the figure can never go down or exceed the amount captured.
 *
 * It does NOT call GrantEnrollment or RevokeEnrollment. Those own granting and
 * deliberate revocation by a person; a refund is a system event with no actor,
 * and — like ExpireEnrollments — it records itself in the audit log with no
 * user and then tells the student. Nothing here can GRANT access.
 *
 * Certificates already issued are left alone: whether a refund voids a
 * certificate is a policy decision nobody has made, and silently deleting one
 * is not reversible.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class ApplyRefund
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EnrollmentAccessService $access,
        private readonly PaymentGateway $gateway,
    ) {}

    public function handle(GatewayPayment $gatewayPayment, GatewayRefund $refund): Payment
    {
        $refundedTotal = $gatewayPayment->amountRefunded
            ?? $this->gateway->fetchPayment($gatewayPayment->id)->amountRefunded
            ?? throw new DomainException(
                "Refund {$refund->id} on payment {$gatewayPayment->id}: the gateway did not report the refunded total.",
            );

        [$payment, $revoked] = DB::transaction(function () use ($gatewayPayment, $refund, $refundedTotal): array {
            $payment = Payment::query()
                ->where('gateway_payment_id', $gatewayPayment->id)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                // A refund for a payment this application never recorded. It
                // may simply have arrived before payment.captured was settled,
                // so fail loudly and let the job's backoff retry it, rather
                // than guess. AlertOnFailedJob raises the alert if it never
                // resolves.
                throw new DomainException(
                    "Refund {$refund->id} references unknown payment {$gatewayPayment->id}.",
                );
            }

            // Absolute, monotonic, capped at what was captured.
            $total = min($payment->amount, max($payment->refunded_amount, $refundedTotal));
            $full = $total >= $payment->amount;

            $alreadyApplied = $total === $payment->refunded_amount
                && (! $full || $payment->status === PaymentStatus::Refunded);

            if ($alreadyApplied) {
                return [$payment, null];
            }

            $before = ['status' => $payment->status->value, 'refunded_amount' => $payment->refunded_amount];

            $payment->forceFill([
                'refunded_amount' => $total,
                'status' => $full ? PaymentStatus::Refunded : $payment->status,
            ])->save();

            $this->audit->record(
                action: 'payment.refunded',
                subject: $payment,
                changes: [
                    'before' => $before,
                    'after' => ['status' => $payment->status->value, 'refunded_amount' => $total],
                ],
                description: sprintf(
                    '%s refund of %d paise on payment %s (refund %s); %d of %d refunded in total.',
                    $full ? 'Full' : 'Partial',
                    $refund->amount,
                    $payment->gateway_payment_id,
                    $refund->id,
                    $total,
                    $payment->amount,
                ),
            );

            if (! $full) {
                return [$payment, null];
            }

            return [$payment, $this->revokeAccess($payment, $refund)];
        });

        if ($revoked !== null) {
            // After the commit and with the row already dead, so a failing
            // mail transport can never leave a refunded student with access.
            EnrollmentRevoked::dispatch($revoked, 'Your payment for this course was refunded.', true);
        }

        $this->access->flush();

        return $payment;
    }

    /**
     * Mark the order refunded and revoke the enrollment it bought — only if no
     * other captured payment still stands behind that order.
     */
    private function revokeAccess(Payment $payment, GatewayRefund $refund): ?Enrollment
    {
        $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->first();

        if ($order === null) {
            return null;
        }

        $stillPaid = $order->payments()
            ->whereKeyNot($payment->getKey())
            ->where('status', PaymentStatus::Captured)
            ->exists();

        if ($stillPaid) {
            return null;
        }

        $order->forceFill(['status' => OrderStatus::Refunded])->save();

        $enrollment = Enrollment::query()
            ->where('order_id', $order->getKey())
            ->where('status', '!=', EnrollmentStatus::Refunded)
            ->lockForUpdate()
            ->first();

        if ($enrollment === null) {
            return null;
        }

        $previous = $enrollment->status;
        $reason = sprintf('Refunded: payment %s (refund %s).', $payment->gateway_payment_id, $refund->id);

        $enrollment->forceFill([
            'status' => EnrollmentStatus::Refunded,
            'revoked_at' => now(),
            'revoked_by' => null,
            'revoke_reason' => $reason,
        ])->save();

        $this->audit->record(
            action: 'enrollment.revoked',
            subject: $enrollment,
            changes: [
                'before' => ['status' => $previous->value],
                'after' => ['status' => EnrollmentStatus::Refunded->value, 'reason' => $reason],
            ],
            description: sprintf('Revoked enrollment #%d — %s', $enrollment->getKey(), $reason),
        );

        return $enrollment;
    }
}
