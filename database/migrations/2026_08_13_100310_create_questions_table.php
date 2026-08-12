<?php

declare(strict_types=1);

use App\Enums\QuestionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| questions — belongs to an assessment
|--------------------------------------------------------------------------
|
| PHASE 3, TRACK B. architecture.md §6.4.
|
| TABLE CLASSIFICATION (planning.md rule S-5): TENANT-OWNED.
|
| ON DELETE CASCADE from assessments: a question has no meaning without its
| assessment (Phase 3 DoD — "deleting an assessment cascades to questions and
| options").
|
| `negative_marks` defaults to 0 rather than being nullable: grading
| arithmetic (Phase 8) always subtracts it, so a non-null default keeps that
| code free of null-coalescing at every call site. `negative_marking_enabled`
| on the parent assessment is the actual on/off switch (FR-ASMT-05).
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();

            $table->string('type');
            $table->text('body');
            $table->text('explanation')->nullable();

            $table->decimal('marks', 6, 2);
            $table->decimal('negative_marks', 6, 2)->default(0);

            $table->unsignedInteger('position')->default(0);

            // e.g. accepted answers for short_answer, matching config later
            // (architecture.md §6.4) — never independently structured tables.
            $table->jsonb('meta')->nullable();

            $table->timestamps();

            $table->index(['assessment_id', 'position']);
        });

        // CHECK constraint mirroring the PHP enum (ADR-012).
        $types = self::quoted(QuestionType::values());

        DB::statement("ALTER TABLE questions ADD CONSTRAINT questions_type_check CHECK (type IN ({$types}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
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
