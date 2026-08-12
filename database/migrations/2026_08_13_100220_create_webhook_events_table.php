<?php

declare(strict_types=1);

use App\Enums\WebhookStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| webhook_events — the idempotency ledger for gateway callbacks
|--------------------------------------------------------------------------
|
| PHASE 3, TRACK C. architecture.md §6.4, §13.
|
| TABLE CLASSIFICATION (planning.md rule S-5): TENANT-OWNED.
|
| ZERO CROSS-TRACK DEPENDENCY — no FK to any other Phase 3 table, so this
| migration runs first, before anything else in this track.
|
| `UNIQUE(event_id)` is the idempotency key (architecture.md §6.5): the
| webhook controller inserts before dispatching `ProcessPaymentWebhook`, and
| a replayed delivery collides on this constraint rather than being
| processed twice. `event_id` is the gateway's own event identifier, not
| ours to generate.
|
| Written by a signature-verified, UNAUTHENTICATED endpoint (Razorpay calls
| it directly) and read only by `ProcessPaymentWebhook`. No user, admin or
| instructor screen touches this table in Phase 3 — confirmed against
| architecture.md §11 and §13 before skipping a policy here (Phase 3 DoD
| conversation, PROGRESS.md).
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();

            // Single gateway in V1 (Razorpay) — a plain string, not an enum:
            // architecture.md never lists more than one legal value, unlike
            // `status` below, so there is nothing for a CHECK constraint to
            // mirror yet (ADR-012 applies to closed sets with more than one
            // legal value).
            $table->string('gateway');

            $table->string('event_id')->unique();
            $table->string('event_type');
            $table->jsonb('payload');
            $table->string('signature');

            $table->string('status')->default(WebhookStatus::Received->value);
            $table->unsignedInteger('attempts')->default(0);

            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            // Backs the "webhook_events.status = failed" monitoring alert
            // (architecture.md §13).
            $table->index('status');
        });

        // CHECK constraint mirroring the PHP enum (ADR-012).
        $statuses = self::quoted(WebhookStatus::values());

        DB::statement("ALTER TABLE webhook_events ADD CONSTRAINT webhook_events_status_check CHECK (status IN ({$statuses}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
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
