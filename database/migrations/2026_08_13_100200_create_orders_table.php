<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| orders — one purchase attempt
|--------------------------------------------------------------------------
|
| PHASE 3, TRACK C. architecture.md §6.4, §11.
|
| TABLE CLASSIFICATION (planning.md rule S-5): TENANT-OWNED.
| `order_number` is COMPOSITE-READY — becomes (organisation_id, order_number)
| in V2, the same pattern as `courses.slug` (see that migration).
|
| DEPENDS ON `courses` (Track A, `100110`) — the block this waits on is
| already on `main`, so this migrates today with no further waiting.
|
| TWO COLUMNS CARRY DECISIONS, NOT JUST DATA:
|
|   user_id — NULLABLE, RESTRICT via SET NULL (nullOnDelete). A buyer may not
|       have an account yet when an order is created (the same nullable-until-
|       resolved mechanism as `users.password`, architecture.md §6.4). Once
|       resolved, deleting that user must never delete their purchase history
|       (NFR-DATA-05) — `buyer_name`/`buyer_email`/`buyer_phone` are captured
|       independently on the order for exactly this reason, mirroring
|       `audit_logs.user_id`'s SET NULL rather than CASCADE.
|
|   amount_subtotal / discount_amount / amount_total — bigint in PAISE
|       (ADR-007), never decimal or float. Same reasoning as
|       `courses.price_amount` — see that migration's docblock.
|
| `course_id` is NOT nullable and uses RESTRICT: an order must always name
| what was purchased, so there is nothing to null it to, and deleting a
| course that has been sold must not silently orphan the purchase record.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('order_number')->unique();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();

            $table->string('buyer_name');
            $table->string('buyer_email');
            $table->string('buyer_phone')->nullable();

            // Money: integer paise. See class docblock.
            $table->bigInteger('amount_subtotal');
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('amount_total');
            $table->char('currency', 3)->default('INR');

            $table->string('status')->default(OrderStatus::Created->value);

            // Single gateway in V1 — see webhook_events migration for why
            // this is a plain string, not an enum.
            $table->string('gateway')->default('razorpay');
            $table->string('gateway_order_id')->nullable()->unique();

            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->jsonb('meta')->nullable();

            $table->timestamps();

            $table->index('buyer_email');
            $table->index(['status', 'created_at']);
            $table->index(['course_id', 'status']);
        });

        // CHECK constraint mirroring the PHP enum (ADR-012).
        $statuses = self::quoted(OrderStatus::values());

        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ({$statuses}))");

        // Money can never be negative. Not the stricter `> 0` courses uses
        // (ADR-014) — a fully-discounted order legitimately totals 0 — just
        // the invariant that is unconditionally true regardless of business
        // rules not yet decided at this layer (those belong to Phase 12's
        // checkout Action, Govind's territory).
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_amount_subtotal_non_negative_check CHECK (amount_subtotal >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_discount_amount_non_negative_check CHECK (discount_amount >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_amount_total_non_negative_check CHECK (amount_total >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
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
