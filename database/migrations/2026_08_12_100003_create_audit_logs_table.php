<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| audit_logs — append-only record of security- and money-relevant actions
|--------------------------------------------------------------------------
|
| PHASE 2. architecture.md §6.4, §18.4. NFR-SEC-16, NFR-SEC-17.
|
| TABLE CLASSIFICATION (rule S-5): TENANT-OWNED — an audit entry concerns one
| organisation's data and must never be readable across organisations in V2.
|
| APPEND-ONLY, ENFORCED BY ABSENCE:
| There is deliberately NO `updated_at` column. A record that can be updated
| is not an audit log — it is a mutable table that merely looks like one.
| The AuditLog model has no update or delete path, AuditLogPolicy denies
| `create` to every user (the service writes entries, never a user action),
| and nothing in the application ever calls save() on an existing entry.
|
| `user_id` is NULLABLE and uses ON DELETE SET NULL rather than CASCADE:
| deleting a user must NEVER erase the record of what they did. That is the
| entire point of an audit trail. The actor's identity is additionally
| captured in `description` at write time so the entry stays meaningful even
| after the account is gone.
|
| `changes` holds {before, after} so an admin can answer "what exactly did
| this change?" — the question an audit log exists to answer.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Dot-notated verb, e.g. auth.login.succeeded, user.role.changed.
            $table->string('action')->index();

            // Polymorphic subject. Nullable: some actions (a failed login for
            // an email that does not exist) have no subject record.
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();

            // Human-readable summary, resolved at WRITE time so it survives
            // the deletion or renaming of the records it refers to.
            $table->text('description')->nullable();

            $table->jsonb('changes')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // No updated_at — see class docblock.
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['auditable_type', 'auditable_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
