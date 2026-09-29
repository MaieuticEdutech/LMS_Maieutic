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
     * An optional postal address, and a second one for when it differs from
     * the first.
     *
     * ALL FREE TEXT. Country, state and district are deliberately not enums
     * or foreign keys to a places table — that needs a real geographic
     * dataset (world or even India-only), which is a materially bigger
     * addition than anything else on this form and was declined when this
     * was scoped. A CHECK constraint on a free-text place name would just
     * reject real addresses.
     *
     * `permanent_address_same_as_correspondence` DEFAULTS TO TRUE, so an
     * account with no address on file reads as "the two would be the same" —
     * the same default the form itself pre-selects — rather than as an
     * unanswered question. When it is true, the permanent_address_* columns
     * are ignored (and kept null); the form never asks for a second address
     * unless someone actually says the two differ.
     *
     * Every column nullable and optional on the form, like the rest of the
     * profile's personal details (NFR-DATA-01). Nobody is required to state
     * an address to correct their name.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('address_country')->nullable()->after('pan_number');
            $table->string('address_state')->nullable()->after('address_country');
            $table->string('address_district')->nullable()->after('address_state');
            $table->string('address_city')->nullable()->after('address_district');
            $table->string('address_line_1')->nullable()->after('address_city');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            // Not numeric: formats vary by country, and some have none at all
            // (hence the form's "enter 0" convention for those).
            $table->string('address_pincode', 12)->nullable()->after('address_line_2');

            $table->boolean('permanent_address_same_as_correspondence')->default(true)->after('address_pincode');
            $table->string('permanent_address_country')->nullable()->after('permanent_address_same_as_correspondence');
            $table->string('permanent_address_state')->nullable()->after('permanent_address_country');
            $table->string('permanent_address_district')->nullable()->after('permanent_address_state');
            $table->string('permanent_address_city')->nullable()->after('permanent_address_district');
            $table->string('permanent_address_line_1')->nullable()->after('permanent_address_city');
            $table->string('permanent_address_line_2')->nullable()->after('permanent_address_line_1');
            $table->string('permanent_address_pincode', 12)->nullable()->after('permanent_address_line_2');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'address_country', 'address_state', 'address_district', 'address_city',
                'address_line_1', 'address_line_2', 'address_pincode',
                'permanent_address_same_as_correspondence',
                'permanent_address_country', 'permanent_address_state', 'permanent_address_district', 'permanent_address_city',
                'permanent_address_line_1', 'permanent_address_line_2', 'permanent_address_pincode',
            ]);
        });
    }
};
