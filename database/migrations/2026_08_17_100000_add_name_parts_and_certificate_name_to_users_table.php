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
     * Split the learner's name and record how they want it on a certificate.
     *
     * ═════════════════════════════════════════════════════════════════════
     * `name` STAYS, AND IS NOT REPLACED BY first_name + last_name.
     *
     * The obvious move is to drop `name` now that the parts exist. It would
     * also be a large, risky refactor for no visible gain: `name` is read by
     * every transactional email, the admin student and instructor tables, the
     * instructor's student lists, the seeders and a great deal of the test
     * suite. Replacing it means touching all of that at once.
     *
     * So `name` remains the canonical DISPLAY name and is kept in step by
     * UpdateProfile whenever the parts change. The parts are what a person
     * edits; `name` is what the rest of the system reads. One writer, no
     * ambiguity about which is authoritative.
     * ═════════════════════════════════════════════════════════════════════
     *
     * ALL THREE ARE NULLABLE, INCLUDING WHERE THE FORM REQUIRES THEM.
     *
     * Every existing account predates these columns, and accounts arrive by
     * three routes — self-registration, an administrator creating one, and
     * (from Phase 12) a purchase. Only the first involves a person filling in
     * a form. A NOT NULL constraint would break the other two and every
     * existing row.
     *
     * `phone` is the same case and is deliberately left nullable: the profile
     * form now requires it, the database does not. That distinction is the
     * point — "a learner must supply this before they can save their profile"
     * is a product rule, not a storage invariant, and encoding it as a
     * constraint would stop an administrator creating a student on a phone
     * call.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');

            /*
             * How the learner wants to be named on a certificate.
             *
             * Kept separate from first/last rather than derived, because the
             * two are genuinely different: a legal or formal name belongs on a
             * credential, and it is not always the name someone uses day to
             * day. Reading it off the display name would put a nickname on a
             * document meant to be shown to an employer.
             *
             * Null means "no preference" — a certificate should then fall back
             * to the display name rather than print nothing.
             */
            $table->string('certificate_name')->nullable()->after('last_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['first_name', 'last_name', 'certificate_name']);
        });
    }
};
