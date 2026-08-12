<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| users — the identity table
|--------------------------------------------------------------------------
|
| PHASE 2. architecture.md §6.4.
|
| TABLE CLASSIFICATION (planning.md rule S-5): TENANT-OWNED.
| In V2 this gains `organisation_id` and the unique index on `email` becomes
| composite — or stays global, depending on PD-11. Either way it is scoped by
| organisation, never platform-global.
|
| TWO COLUMNS CARRY THE SECURITY WEIGHT:
|
|   password — NULLABLE, and that is the point (architecture.md §6.4).
|       A purchase-created account (Phase 12) exists before its owner has ever
|       chosen a password. NULL cannot satisfy Hash::check() under any input,
|       so such an account is structurally unable to authenticate until it is
|       activated. That is a fail-safe default: forgetting a status check
|       somewhere still cannot log the account in.
|
|   status — only `active` may authenticate, asserted inside
|       Fortify::authenticateUsing() rather than in middleware alone, so no
|       session is ever established for a suspended user (§7.1.1).
|
| role and status are CHECK-constrained against the PHP enums (ADR-012), so
| the database rejects an illegal value even if application code is bypassed
| by a console command, a seeder or a future developer's raw query.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            // Stored normalised lower-case. FR-AUTH-10: A@X.com and a@x.com
            // are ONE account. Enforced by the User model's mutator and by
            // Fortify's lowercase_usernames, belt and braces.
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();

            // See class docblock — nullable is a security property, not laziness.
            $table->string('password')->nullable();

            $table->string('role')->default(UserRole::Student->value);
            $table->string('status')->default(UserStatus::PendingVerification->value);

            $table->string('phone')->nullable();
            $table->string('avatar_path')->nullable();
            $table->timestamp('last_login_at')->nullable();

            $table->rememberToken();
            $table->timestamps();

            // Soft delete: NFR-DATA-05. Deleting a student must never orphan
            // their financial records, so the row survives and is excluded by
            // the global soft-delete scope.
            $table->softDeletes();

            $table->index(['role', 'status']);
            $table->index(['status', 'created_at']);
        });

        // CHECK constraints mirroring the PHP enums (ADR-012). PostgreSQL
        // native enum types are deliberately avoided: adding a value to one
        // requires ALTER TYPE, which does not compose well with migrations.
        $roles = self::quoted(UserRole::values());
        $statuses = self::quoted(UserStatus::values());

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ({$roles}))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ({$statuses}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
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
