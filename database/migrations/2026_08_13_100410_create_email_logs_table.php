<?php

declare(strict_types=1);

use App\Enums\EmailStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| email_logs — every outbound mailable, logged (FR-MAIL-10)
|--------------------------------------------------------------------------
|
| PHASE 3, TRACK C. architecture.md §6.4, §14.
|
| TABLE CLASSIFICATION (planning.md rule S-5): TENANT-OWNED.
|
| ZERO CROSS-TRACK DEPENDENCY — no FK to any other Phase 3 table.
|
| Written only by `SendMailJob` (architecture.md §14) after every mailable
| dispatch — never by user input. No FK to `users`: `to_email` is a plain
| string because a purchase-created account may not exist yet when the first
| email is sent (architecture.md §6.4's nullable-password mechanism), and a
| log entry must not depend on the row it might be describing.
|
| No index beyond the primary key: unlike `webhook_events`, nothing in
| architecture.md names a specific query this table must serve yet (no
| "email_logs.status = failed" alert is listed in §17's monitoring baseline,
| the way one is for webhook_events in §13). Indexing ahead of a known query
| pattern is a Phase 13 (Reporting) decision, not a Phase 3 one.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();

            $table->string('to_email');
            $table->string('mailable');
            $table->string('subject');

            $table->string('status')->default(EmailStatus::Queued->value);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->jsonb('context')->nullable();

            $table->timestamps();
        });

        // CHECK constraint mirroring the PHP enum (ADR-012).
        $statuses = self::quoted(EmailStatus::values());

        DB::statement("ALTER TABLE email_logs ADD CONSTRAINT email_logs_status_check CHECK (status IN ({$statuses}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
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
