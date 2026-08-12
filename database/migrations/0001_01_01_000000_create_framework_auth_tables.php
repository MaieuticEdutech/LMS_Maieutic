<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Framework authentication infrastructure
|--------------------------------------------------------------------------
|
| PHASE 1 — Project Foundation.
|
| This is the stock Laravel `create_users_table` migration with the `users`
| table REMOVED. `users` is a domain table owned by Phase 2 (Identity,
| Authentication & RBAC), where it is created with the LMS columns: role,
| status, nullable password, normalised email, soft deletes and CHECK
| constraints (architecture.md §6.4). Phase 1's Definition of Done states
| "No domain tables yet", so creating a stock `users` table here would both
| violate that boundary and force Phase 2 to rewrite a migration.
|
| Splitting is safe: neither table below has a foreign key to `users`.
| `sessions.user_id` is an unconstrained indexed column, and
| `password_reset_tokens` is keyed by email address.
|
| TABLE CLASSIFICATION (planning.md rule S-5):
|   password_reset_tokens — TENANT-OWNED. Keyed by email. If V2 makes user
|       identity per-organisation (PD-11), this becomes organisation-scoped
|       alongside `users`. If identity stays global, it stays global with it.
|       It follows whatever `users` does; it is never independently scoped.
|   sessions              — PLATFORM-GLOBAL. Web-tier infrastructure. A session
|       belongs to a browser, not to an organisation; the organisation is
|       resolved per request from the tenant context (architecture.md §24.2).
|
*/
return new class extends Migration
{
    public function up(): void
    {
        // Serves BOTH password reset and first-time account activation.
        // Tokens are stored hashed, expiring and single-use by Laravel's
        // password broker — which is why the LMS has no bespoke activation
        // token table (ADR-004, FR-AUTH-05, FR-MAIL-03).
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
    }
};
