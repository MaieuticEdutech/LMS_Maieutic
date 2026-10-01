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
     * A profile photo and an Aadhaar card scan.
     *
     * THE FILE CONTENTS ARE ENCRYPTED; THE PATH COLUMNS ARE NOT, AND THAT IS
     * DELIBERATE. Every byte is written to a disk of its own
     * (config/filesystems.php's `profile_documents`, `serve => false`) only
     * as ciphertext, by ProfileDocumentStorage, and read back only through an
     * authorised controller — never a direct storage URL. What sits in these
     * *_path columns is just a location reference, not the document itself;
     * it carries no more sensitivity than a filename, so it is a plain string
     * (aadhaar_document_path is `text` only because a nested path can run
     * long, not because it holds ciphertext). This is a deliberately
     * separate, smaller system from `media_files` (course content): the two
     * have different owners, different config, and different risk profiles,
     * and this was built to stay out of that system rather than extend it.
     *
     * `avatar_path` ALREADY EXISTS (2026_08_12 users migration) and has no
     * reader anywhere in the codebase today, so it is reused here as the
     * photo's storage path rather than adding a near-duplicate column. Its
     * three metadata columns are new.
     *
     * `aadhaar_document_*` gets the FULL set including a new path column. Both
     * document kinds' *_path columns are intentionally left OFF User::$fillable
     * — see the model — the same reason MediaFile keeps its own `path` off its
     * fillable list: a path is written only by ProfileDocumentStorage, from a
     * file that was actually uploaded and validated, never from a bare string
     * a request could supply.
     *
     * ALL NULLABLE AND OPTIONAL. Nobody is required to upload either document
     * to use their profile.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_original_name')->nullable()->after('avatar_path');
            $table->string('avatar_mime_type', 100)->nullable()->after('avatar_original_name');
            $table->unsignedInteger('avatar_size_bytes')->nullable()->after('avatar_mime_type');

            $table->text('aadhaar_document_path')->nullable()->after('avatar_size_bytes');
            $table->string('aadhaar_document_original_name')->nullable()->after('aadhaar_document_path');
            $table->string('aadhaar_document_mime_type', 100)->nullable()->after('aadhaar_document_original_name');
            $table->unsignedInteger('aadhaar_document_size_bytes')->nullable()->after('aadhaar_document_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'avatar_original_name', 'avatar_mime_type', 'avatar_size_bytes',
                'aadhaar_document_path', 'aadhaar_document_original_name', 'aadhaar_document_mime_type', 'aadhaar_document_size_bytes',
            ]);
        });
    }
};
