<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| payments — one payment attempt against an order
|--------------------------------------------------------------------------
|
| PHASE 3, TRACK C. architecture.md §6.4, §11.
|
| TABLE CLASSIFICATION (planning.md rule S-5): TENANT-OWNED.
|
| DEPENDS ON `orders` (this track's own `100200`, already migrated).
|
| Separate from `orders`: one order legitimately has several payment
| attempts (fail → retry → capture), and Razorpay models order and payment
| as distinct objects. `order_id` is not nullable and uses RESTRICT — a
| payment record must always name the order it was for, and deleting an
| order that has payment history must not silently orphan it (same
| reasoning as `orders.course_id`, see that migration).
|
| `UNIQUE(gateway_payment_id)` (architecture.md §6.5): one gateway payment,
| one record — the idempotency guarantee for the capture side of the webhook
| flow, the counterpart to `webhook_events.event_id` on the receiving side.
|
| Money: integer paise (ADR-007), never decimal or float — same reasoning as
| `orders`' money columns.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->index('order_id');

            // Single gateway in V1 — see webhook_events migration for why
            // this is a plain string, not an enum.
            $table->string('gateway')->default('razorpay');
            $table->string('gateway_payment_id')->unique();

            // Gateway-reported payment method (card, upi, netbanking, ...).
            // Open-ended and gateway-defined, so a plain string, not an enum
            // — nullable because it isn't known until the attempt completes.
            $table->string('method')->nullable();

            $table->bigInteger('amount');
            $table->char('currency', 3)->default('INR');

            $table->string('status')->default(PaymentStatus::Created->value);
            $table->timestamp('captured_at')->nullable();
            $table->bigInteger('refunded_amount')->default(0);

            $table->string('failure_code')->nullable();
            $table->text('failure_reason')->nullable();
            $table->jsonb('raw_payload')->nullable();

            $table->timestamps();
        });

        // CHECK constraint mirroring the PHP enum (ADR-012).
        $statuses = self::quoted(PaymentStatus::values());

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ({$statuses}))");

        // Money can never be negative — same reasoning as `orders` (ADR-007).
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_non_negative_check CHECK (amount >= 0)');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_refunded_amount_non_negative_check CHECK (refunded_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }

    /**
     * @param  list<string>  $values
     */
    private static function quoted(array $values): string
    {
        return implode(', ', array_map(
            static fn (string $value): string => "'".str_replace("'", "''", $value)."'",
            $values,
        ));
    }
};
