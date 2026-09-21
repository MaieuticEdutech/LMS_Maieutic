<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Actions\Enrollment\GrantEnrollment;
use App\Actions\Identity\SendActivationLink;
use App\Enums\EnrollmentSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PurchaseConfirmationNotification;
use App\Services\Audit\AuditLogger;
use App\Support\Payment\GatewayPayment;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Settle one verified, captured payment (architecture.md §11.2's queue-side
 * steps, §11.3 rules 5 and 9).
 *
 * The ONLY caller of {@see GrantEnrollment} on the purchase path — reached
 * from {@see \App\Jobs\Payment\ProcessPaymentWebhook} (the webhook, the
 * normal case) and {@see \App\Console\Commands\ReconcileOrders} (the
 * self-healing case, architecture.md §11.3 rule 8). Both call this same
 * Action rather than duplicating the settlement logic, so a webhook that
 * arrives AND a reconciliation sweep that runs for the same order cannot
 * disagree about what happened.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * IDEMPOTENCY, THREE LAYERS DEEP.
 *
 *   1. `payments.gateway_payment_id` UNIQUE — this class checks it first and
 *      returns the existing row untouched. A retried webhook for a payment
 *      already settled does nothing.
 *   2. The order row is locked (`lockForUpdate`) before its status is read,
 *      so a webhook and a concurrent reconciliation sweep for the SAME order
 *      cannot both observe "not yet paid" and both proceed.
 *   3. {@see GrantEnrollment} is itself idempotent (its own two layers) —
 *      called unconditionally here, every time, so this class never has to
 *      get "should I grant" right on its own.
 *
 * Layer 1 is why this method never sends a second purchase-confirmation
 * email for the same payment: the early return happens before anything is
 * dispatched.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class SettleCapturedPayment
{
    public function __construct(
        private readonly GrantEnrollment $grantEnrollment,
        private readonly SendActivationLink $sendActivationLink,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(GatewayPayment $gatewayPayment): Payment
    {
        $existing = Payment::query()
            ->where('gateway_payment_id', $gatewayPayment->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        [$payment, $order, $buyer, $buyerWasCreated, $course] = DB::transaction(function () use ($gatewayPayment): array {
            $order = Order::query()
                ->where('gateway_order_id', $gatewayPayment->orderId)
                ->with('course')
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                // A payment for an order this application never created.
                // Never guess an order to attach it to — that would let a
                // malformed or malicious payload grant access to whatever
                // order happens to be locked next. Let the job fail loudly;
                // AlertOnFailedJob turns that into an operational alert
                // (architecture.md §11.3 rule 5's "alert raised").
                throw new DomainException(
                    "Payment {$gatewayPayment->id} references unknown gateway order {$gatewayPayment->orderId}.",
                );
            }

            if ($gatewayPayment->amount !== $order->amount_total || $gatewayPayment->currency !== $order->currency) {
                $this->audit->record(
                    action: 'security.payment_amount_mismatch',
                    subject: $order,
                    description: sprintf(
                        'Payment %s reported %d %s against order %s expecting %d %s — rejected.',
                        $gatewayPayment->id,
                        $gatewayPayment->amount,
                        $gatewayPayment->currency,
                        $order->order_number,
                        $order->amount_total,
                        $order->currency,
                    ),
                );

                throw new DomainException(
                    "Amount/currency mismatch on order {$order->order_number}: no enrollment granted.",
                );
            }

            // NOT ->payments()->create(): 'amount' and 'status' are
            // deliberately absent from Payment::$fillable (NFR-SEC-07), so
            // create()'s single INSERT would omit them and 'amount' has no
            // schema default — violating payments.amount's NOT NULL
            // constraint before a follow-up forceFill+save ever ran. Building
            // the model, then forceFill-ing every column, then ONE save()
            // is the same one-INSERT shape every other Action in this
            // codebase uses for a model with guarded financial columns (see
            // InitiateCheckout's own Order construction).
            $payment = new Payment([
                'gateway' => 'razorpay',
                'gateway_payment_id' => $gatewayPayment->id,
                'method' => $gatewayPayment->method,
                'currency' => $gatewayPayment->currency,
                'captured_at' => now(),
            ]);
            $payment->order()->associate($order);
            $payment->forceFill([
                'amount' => $gatewayPayment->amount,
                'status' => PaymentStatus::Captured,
            ])->save();

            $order->forceFill([
                'status' => OrderStatus::Paid,
                'paid_at' => $order->paid_at ?? now(),
            ])->save();

            [$buyer, $buyerWasCreated] = $this->resolveBuyer($order);

            $order->forceFill(['user_id' => $buyer->getKey()])->save();

            // orders.course_id is NOT NULL and RESTRICT (that migration's own
            // docblock) — nullable here only because 'course' was eager
            // loaded above, which PHPStan cannot infer from the query alone.
            // Same defensive-throw convention as SubmitCourseReview /
            // IssueCertificate for a relation the schema guarantees but the
            // type system does not.
            $course = $order->course ?? throw new RuntimeException(
                "Order {$order->order_number} has no course loaded.",
            );

            $this->grantEnrollment->handle(
                student: $buyer,
                course: $course,
                source: EnrollmentSource::Purchase,
                order: $order,
            );

            $this->audit->record(
                action: 'payment.captured',
                subject: $payment,
                description: sprintf(
                    'Payment captured for order %s — %s.',
                    $order->order_number,
                    $order->total,
                ),
            );

            return [$payment, $order, $buyer, $buyerWasCreated, $course];
        });

        // Dispatched after commit (architecture.md §11.3 rule 7): a slow or
        // failing mail transport must never be able to roll back a paid
        // enrollment. DB::afterCommit() rather than ->afterCommit() on the
        // notification itself, so this runs even for a notification class
        // that does not implement ShouldQueue.
        //
        // Notified via the $buyer resolved inside the transaction, not
        // $order->user — that relation was never eager-loaded on $order, and
        // reading it here would lazily reload it (Model::preventLazyLoading
        // is active outside production, architecture.md's own strict-mode
        // posture).
        DB::afterCommit(function () use ($order, $buyer, $buyerWasCreated, $course): void {
            if ($buyerWasCreated) {
                // The ONLY way a purchase-created account becomes usable — see
                // AccountActivationNotification's own docblock.
                $this->sendActivationLink->handle($buyer);
            }

            $buyer->notify(new PurchaseConfirmationNotification(
                orderNumber: $order->order_number,
                courseTitle: $course->title,
                amountPaid: $order->total,
                paidAt: $order->paid_at ?? now(),
            ));
        });

        return $payment;
    }

    /**
     * Find the buyer's existing account by their normalised checkout email,
     * or create one exactly as a purchase-created account must look
     * (architecture.md §7.2, UserStatus's own docblock): `PendingActivation`,
     * `password` left NULL, role always `Student`.
     *
     * Mirrors {@see \App\Actions\Identity\RegisterStudent} in shape — role
     * and status are set explicitly here, never mass-assigned, for the same
     * NFR-SEC-07 reason.
     *
     * @return array{0: User, 1: bool} The buyer, and whether this call created them.
     */
    private function resolveBuyer(Order $order): array
    {
        $email = mb_strtolower(trim($order->buyer_email));

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        $user = new User;

        $user->fill([
            'name' => $order->buyer_name,
            'email' => $email,
        ]);

        $user->forceFill([
            'role' => UserRole::Student,
            'status' => UserStatus::PendingActivation,
            'password' => null,
        ]);

        $user->save();

        return [$user, true];
    }
}
