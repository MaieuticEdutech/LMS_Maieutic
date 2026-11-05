<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * TENANCY CLASSIFICATION: TENANT-OWNED.
 *
 * `users` is tenant-owned (see the table's own migration).
 */
return new class extends Migration
{
    /**
     * Learners no longer upload a profile photo or an Aadhaar card scan, so the
     * columns that tracked those files go. 2026_08_12_100000 (avatar_path) and
     * 2026_09_28_100500 (the metadata and aadhaar_document_*) are already
     * merged and are never edited, hence a new migration.
     *
     * The platform launched with no learners, so there are no stored files to
     * remove alongside the columns.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'avatar_path', 'avatar_original_name', 'avatar_mime_type', 'avatar_size_bytes',
                'aadhaar_document_path', 'aadhaar_document_original_name', 'aadhaar_document_mime_type', 'aadhaar_document_size_bytes',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_path')->nullable();
            $table->string('avatar_original_name')->nullable()->after('avatar_path');
            $table->string('avatar_mime_type', 100)->nullable()->after('avatar_original_name');
            $table->unsignedInteger('avatar_size_bytes')->nullable()->after('avatar_mime_type');

            $table->text('aadhaar_document_path')->nullable()->after('avatar_size_bytes');
            $table->string('aadhaar_document_original_name')->nullable()->after('aadhaar_document_path');
            $table->string('aadhaar_document_mime_type', 100)->nullable()->after('aadhaar_document_original_name');
            $table->unsignedInteger('aadhaar_document_size_bytes')->nullable()->after('aadhaar_document_mime_type');
        });
    }
};
