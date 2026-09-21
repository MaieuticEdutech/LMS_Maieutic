<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Notifications\PaymentFailedNotification;
use App\Services\Audit\AuditLogger;
use App\Support\Payment\GatewayPayment;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Record a `payment.failed` event (architecture.md §11.4's `pending ->
 * failed` transition).
 *
 * Deliberately NOT a caller of {@see \App\Actions\Enrollment\GrantEnrollment}
 * — a failed payment grants nothing, and this class has no path that could
 * accidentally do so. The order returns to a state the buyer can retry from
 * (§11.4: `failed -> pending` on retry), so nothing here is terminal.
 */
final class RecordFailedPayment
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(GatewayPayment $gatewayPayment): ?Payment
    {
        $existing = Payment::query()
            ->where('gateway_payment_id', $gatewayPayment->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $order = Order::query()
            ->where('gateway_order_id', $gatewayPayment->orderId)
            ->with('course')
            ->first();

        if ($order === null) {
            // Same posture as SettleCapturedPayment: an event for an order
            // this application does not know about is not silently dropped.
            // A failure event carries less urgency than a mismatched capture,
            // so this is audited rather than thrown — there is no money and
            // no access at stake, only a record worth having.
            $this->audit->record(
                action: 'payment.failed_unmatched',
                description: "Failure event for unknown gateway order {$gatewayPayment->orderId}.",
            );

            return null;
        }

        $payment = DB::transaction(function () use ($order, $gatewayPayment): Payment {
            // See SettleCapturedPayment's identical construction for why this
            // is forceFill+save in one INSERT rather than ->create() followed
            // by a second write: 'amount' is not fillable and has no schema
            // default, so create() alone would violate the NOT NULL
            // constraint on payments.amount.
            $payment = new Payment([
                'gateway' => 'razorpay',
                'gateway_payment_id' => $gatewayPayment->id,
                'method' => $gatewayPayment->method,
                'currency' => $gatewayPayment->currency,
                'failure_code' => $gatewayPayment->failureCode,
                'failure_reason' => $gatewayPayment->failureReason,
            ]);
            $payment->order()->associate($order);
            $payment->forceFill([
                'amount' => $gatewayPayment->amount,
                'status' => PaymentStatus::Failed,
            ])->save();

            // Only when the order has not already succeeded by another path
            // (a delayed failure notification for an order reconciliation
            // already settled must not un-pay it).
            if ($order->status !== OrderStatus::Paid) {
                $order->forceFill([
                    'status' => OrderStatus::Failed,
                    'failed_reason' => $gatewayPayment->failureReason,
                ])->save();
            }

            return $payment;
        });

        $this->audit->record(
            action: 'payment.failed',
            subject: $payment,
            description: sprintf(
                'Payment failed for order %s — %s.',
                $order->order_number,
                $gatewayPayment->failureReason ?? 'no reason given by gateway',
            ),
        );

        if ($order->status === OrderStatus::Failed) {
            // orders.course_id is NOT NULL and RESTRICT (see that migration)
            // — this can only be null if 'course' was never eager-loaded,
            // which it is, above. Same defensive-throw convention as
            // SubmitCourseReview / IssueCertificate for a relation PHPStan
            // sees as nullable but the schema guarantees is not.
            $course = $order->course ?? throw new RuntimeException(
                "Order {$order->order_number} has no course loaded.",
            );

            DB::afterCommit(function () use ($order, $course): void {
                // The buyer may not have an account yet — this notifies the
                // checkout email directly rather than routing through a
                // User, the same "possession of the address is the
                // authorisation" reasoning routes/web.php uses for
                // certificate verification, applied to an inbox instead of a
                // URL token.
                (new AnonymousNotifiable)
                    ->route('mail', $order->buyer_email)
                    ->notify(new PaymentFailedNotification(
                        orderNumber: $order->order_number,
                        courseTitle: $course->title,
                        courseSlug: $course->slug,
                        reason: $order->failed_reason,
                    ));
            });
        }

        return $payment;
    }
}
