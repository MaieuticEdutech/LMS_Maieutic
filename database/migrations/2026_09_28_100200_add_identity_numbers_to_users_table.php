<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * TENANCY CLASSIFICATION: TENANT-OWNED.
 *
 * These columns live on `users`, which is tenant-owned (see the table's own
 * migration). A person belongs to one organisation in V2 (planning.md S-1).
 */
return new class extends Migration
{
    /**
     * Optional Aadhaar and PAN numbers, stated by the learner on their profile.
     *
     * ═════════════════════════════════════════════════════════════════════
     * THESE ARE GOVERNMENT IDENTITY NUMBERS. THEY ARE HANDLED DIFFERENTLY
     * FROM EVERY OTHER PROFILE FIELD.
     *
     *   ENCRYPTED AT REST. The model casts both with Laravel's `encrypted`
     *   cast (APP_KEY), so a database dump or a read-only SQL session shows
     *   ciphertext, never the number. Hence TEXT: the ciphertext is far longer
     *   than the 10–12 characters it protects.
     *
     *   NEVER IN THE AUDIT LOG. AuditLogger redacts both names, and the log is
     *   append-only at the database level — anything written there could never
     *   be erased.
     *
     *   NEVER DISPLAYED IN FULL once saved: the profile shows a masked form
     *   (last four characters only), and the model hides both from arrays and
     *   JSON.
     *
     *   NOT UNIQUE, NOT INDEXED, NOT SEARCHABLE. Encryption makes each stored
     *   value different even for the same number, so the database cannot
     *   compare them — which is also what stops these columns being used to
     *   look anyone up.
     *
     * Rotating APP_KEY without re-encrypting makes existing values unreadable.
     * ═════════════════════════════════════════════════════════════════════
     *
     * Both nullable and optional, like every personal detail (NFR-DATA-01).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('aadhaar_number')->nullable()->after('nationality');
            $table->text('pan_number')->nullable()->after('aadhaar_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['aadhaar_number', 'pan_number']);
        });
    }
};
