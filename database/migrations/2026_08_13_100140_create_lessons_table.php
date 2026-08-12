<?php

declare(strict_types=1);

use App\Enums\LessonType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| lessons — the leaf of the content hierarchy
|--------------------------------------------------------------------------
|
| PHASE 3 · TRACK A. architecture.md §6.4, §9.2. FR-CNT-02, FR-CNT-06, FR-CNT-07.
|
| TABLE CLASSIFICATION (rule S-5): TENANT-OWNED.
|
| THE `type` + `meta` PAIR IS THE EXTENSIBILITY MECHANISM (ADR-003, FR-CNT-07):
|
| There is no `videos` table, no `notes` table, no `presentations` table. A
| lesson declares its `type`, its uploaded bytes live in `media_files`, and
| any type-specific attributes live in the `meta` JSONB column. Adding a new
| content type later — SCORM, embedded video, live session — is ONE handler
| class registered in ContentTypeRegistry plus two Blade partials, with
| ZERO schema change here. Four near-identical tables would have forced a
| migration for every new type.
|
| NO `is_preview` COLUMN. Removed by business decision (ADR-014): guests see
| course metadata only, and the access gate has no preview branch. Adding it
| back later is a single additive nullable boolean plus one branch in
| EnrollmentAccessService — but it must be a decision, not a drift.
|
| `body` holds sanitised HTML for `text` lessons. Sanitisation happens on SAVE
| against an allow-list (NFR-SEC-06), never on render — storing hostile markup
| and hoping every read path escapes it is how XSS survives a refactor.
|
| UNIQUE(module_id, slug) keeps lesson URLs stable and unambiguous within a
| module without forcing global slug uniqueness across the whole catalogue.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();

            $table->foreignId('module_id')
                ->constrained('modules')
                ->cascadeOnDelete();

            $table->string('title');
            $table->string('slug');

            $table->string('type')->default(LessonType::Text->value);

            $table->text('summary')->nullable();

            // Sanitised HTML for `text` lessons. See class docblock.
            $table->longText('body')->nullable();

            $table->unsignedInteger('position')->default(0);

            // Drives the course duration cache and the player's time display.
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->boolean('is_published')->default(false);

            // Type-specific attributes. The reason no new content type needs
            // a schema change (FR-CNT-07).
            $table->jsonb('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['module_id', 'slug']);
            $table->index(['module_id', 'position']);
            $table->index(['module_id', 'is_published']);
        });

        $types = self::quoted(LessonType::values());

        DB::statement("ALTER TABLE lessons ADD CONSTRAINT lessons_type_check CHECK (type IN ({$types}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
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
