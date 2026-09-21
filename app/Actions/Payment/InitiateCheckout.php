<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Contracts\Payment\PaymentGateway;
use App\Enums\CourseStatus;
use App\Enums\OrderStatus;
use App\Models\Course;
use App\Models\Order;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Start a purchase (architecture.md §11.2, steps 1-6).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * THE PRICE IS READ FROM THE COURSE, NEVER FROM THE REQUEST.
 *
 * This is architecture.md §11.3 rule 1, and it is the entire reason a
 * checkout Action exists rather than a controller building an Order
 * directly: `$course->price` (a {@see \App\Support\Money}, itself built from
 * `courses.price_amount`) is the only source `amount_subtotal` /
 * `amount_total` may ever come from. A browser cannot discount its own
 * purchase by editing a hidden field, because there is no field — the
 * amount never left the server before this Action reads it.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * NOT a caller of {@see \App\Actions\Enrollment\GrantEnrollment}. Starting a
 * checkout grants nothing — it only ever produces an unpaid `Order`. Access
 * is granted exclusively by {@see SettleCapturedPayment}, from the verified
 * webhook or reconciliation path, per Rule 1.
 */
final class InitiateCheckout
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null}  $buyer
     */
    public function handle(Course $course, array $buyer): Order
    {
        if ($course->status !== CourseStatus::Published) {
            // Deliberately generic — same message a not-found course would
            // produce, so an unpublished course's existence is not
            // distinguishable from a typo in the URL.
            throw ValidationException::withMessages([
                'course' => 'This course is not available for purchase.',
            ]);
        }

        // NO free-course guard here, deliberately: `courses_price_positive_check`
        // (that table's migration, ADR-014 — "all V1 courses are paid") means
        // $course->price->isZero() can never be true for any row this method
        // could ever be called with. A check for it would be dead code
        // guarding against a state the schema already makes impossible.

        $order = DB::transaction(function () use ($course, $buyer): Order {
            $order = new Order;

            $order->fill([
                'order_number' => $this->generateOrderNumber(),
                'course_id' => $course->getKey(),
                'buyer_name' => $buyer['name'],
                'buyer_email' => $buyer['email'],
                'buyer_phone' => $buyer['phone'] ?? null,
                'currency' => $course->currency,
                'gateway' => 'razorpay',
            ]);

            // NOT fillable, set explicitly (NFR-SEC-07): money and status are
            // never mass-assigned. amount_subtotal/amount_total are equal —
            // discounts are a [FUTURE] feature (OrderFactory::discounted()
            // exists for that later Action; nothing in Phase 12 sets it).
            $order->forceFill([
                'amount_subtotal' => $course->price_amount,
                'discount_amount' => 0,
                'amount_total' => $course->price_amount,
                'status' => OrderStatus::Created,
                'placed_at' => now(),
            ]);

            $order->save();

            return $order;
        });

        $gatewayOrder = $this->gateway->createOrder($order);

        $order->forceFill([
            'gateway_order_id' => $gatewayOrder->id,
            'status' => OrderStatus::Pending,
        ])->save();

        $this->audit->record(
            action: 'order.checkout_started',
            subject: $order,
            description: sprintf(
                'Checkout started for "%s" — order %s, %s.',
                $course->title,
                $order->order_number,
                $order->total,
            ),
        );

        return $order;
    }

    /**
     * `ORD-` + 10 random alphanumeric characters, matching
     * {@see \Database\Factories\OrderFactory}'s shape. Collision is checked
     * rather than assumed impossible — `orders.order_number` is UNIQUE at
     * the database, and this loop is the application-level partner to that
     * constraint, not a replacement for it.
     */
    private function generateOrderNumber(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = 'ORD-'.strtoupper(Str::random(10));

            if (! Order::query()->where('order_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException('Could not generate a unique order number after 5 attempts.');
    }
}
