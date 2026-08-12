<?php

declare(strict_types=1);

use App\Enums\MediaPurpose;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| media_files — ONE table for every uploaded byte
|--------------------------------------------------------------------------
|
| PHASE 3 · TRACK A. architecture.md §6.4, §15. ADR-003, FR-FILE-01…14.
|
| TABLE CLASSIFICATION (rule S-5): TENANT-OWNED.
|
| WHY ONE POLYMORPHIC TABLE AND NOT FOUR:
| The brief listed `videos`, `notes`, `presentations` and `resources` as
| candidate tables. They differ only in MIME type and how they render — four
| near-identical schemas, four upload pipelines, four access policies, and a
| migration every time a new file kind appears. Collapsing them means ONE
| upload pipeline to secure and ONE policy to get right, which is the whole
| argument: file access is where this system is most likely to leak, and the
| fewer places it is implemented, the fewer places it can be wrong.
|
| SECURITY-RELEVANT COLUMNS:
|
|   ulid — the PUBLIC handle used in URLs. Sequential integer ids would let
|       anyone enumerate the catalogue's media by counting. Unique, so it can
|       be resolved directly.
|
|   disk + path — resolved ONLY by MediaPathResolver (rule S-2, FR-FILE-11).
|       No other class in the codebase may construct a storage path. That one
|       method is what makes V2's `org/{id}/` prefix a one-line change.
|
|   original_name — kept as METADATA only. The stored filename is always
|       system-generated from the ulid (FR-FILE-05), so a hostile upload
|       called "invoice.pdf.php" never becomes a filename on disk.
|
|   checksum_sha256 — integrity verification and duplicate detection.
|
|   is_downloadable — a video is streamed, never offered as a download. This
|       column is the difference, and it is enforced server-side in Phase 6;
|       hiding a download button is not the control (Rule 20).
|
| NOTE: `attachable` has NO foreign key — that is inherent to a polymorphic
| relation, not an oversight. Orphan cleanup is therefore an explicit job
| (`DeleteOrphanedMedia`, Phase 5), not something the database does for us.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();

            // Public URL handle — non-enumerable. See class docblock.
            $table->ulid('ulid')->unique();

            // Attachable to a Lesson, Course, Question or User.
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');

            // Written and read ONLY through MediaPathResolver (rule S-2).
            $table->string('disk');
            $table->string('path');

            $table->string('original_name');
            $table->string('mime_type');
            $table->string('extension', 16);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64)->nullable();

            $table->string('purpose')->default(MediaPurpose::Attachment->value);

            $table->boolean('is_downloadable')->default(false);
            $table->unsignedInteger('position')->default(0);

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // The read path: ordered media for one lesson/course.
            $table->index(['attachable_type', 'attachable_id', 'position']);

            // Orphan detection and storage reconciliation.
            $table->index(['disk', 'path']);
        });

        $purposes = self::quoted(MediaPurpose::values());

        DB::statement("ALTER TABLE media_files ADD CONSTRAINT media_files_purpose_check CHECK (purpose IN ({$purposes}))");

        // A zero-byte upload means the pipeline failed silently. Refuse it.
        DB::statement('ALTER TABLE media_files ADD CONSTRAINT media_files_size_positive_check CHECK (size_bytes > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
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
