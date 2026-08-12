<?php

declare(strict_types=1);

use App\Enums\CompletionSource;
use App\Enums\ProgressStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| lesson_progress — the only FACT in the progress system
|--------------------------------------------------------------------------
|
| PHASE 3, TRACK C. architecture.md §6.4, §17.1.
|
| TABLE CLASSIFICATION (planning.md rule S-5): TENANT-OWNED.
|
| DEPENDS ON this track's own `enrollments` (`100230`) and `lessons`
| (Track A, already on `main`).
|
| architecture.md §17.1: module and course progress are DERIVED at read time
| (or cached on `enrollments.progress_percentage`) — never stored here.
| Storing all three invites contradiction (P-8). This table is written by
| `RecordLessonProgress` (Phase 9, Govind's) — the storage/meaning split is
| the same one already documented for `enrollments`.
|
| `UNIQUE(enrollment_id, lesson_id)` (architecture.md §6.5) is what makes a
| progress write a safe UPSERT under concurrency (FR-PROG-14, AC-32) — two
| video-tick requests racing for the same lesson resolve to one row, not two.
|
| `enrollment_id` CASCADES (explicitly specified, architecture.md §6.4): a
| progress row has no meaning once its enrollment is gone. `lesson_id` also
| CASCADES for the same reason — the row is meaningless without the lesson
| it describes (same reasoning as `instructor_profiles.user_id`). `user_id`
| is denormalised for query speed but still RESTRICT: in practice this can
| never fire before `enrollments.user_id`'s own RESTRICT already blocks
| deleting a user with an active enrollment.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();

            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();

            // Denormalised for query speed (architecture.md §6.4) — avoids a
            // join through enrollments for every progress read.
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->string('status')->default(ProgressStatus::NotStarted->value);

            $table->unsignedInteger('video_position_seconds')->default(0);
            $table->unsignedInteger('video_watched_seconds')->default(0);
            $table->unsignedInteger('video_duration_seconds')->default(0);

            $table->string('completion_source')->nullable();

            $table->timestamp('first_accessed_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // THE concurrency guarantee — see class docblock.
            $table->unique(['enrollment_id', 'lesson_id']);

            $table->index(['user_id', 'status']);
            $table->index(['lesson_id', 'status']);
        });

        // CHECK constraints mirroring the PHP enums (ADR-012).
        $statuses = self::quoted(ProgressStatus::values());
        $sources = self::quoted(CompletionSource::values());

        DB::statement("ALTER TABLE lesson_progress ADD CONSTRAINT lesson_progress_status_check CHECK (status IN ({$statuses}))");
        DB::statement("ALTER TABLE lesson_progress ADD CONSTRAINT lesson_progress_completion_source_check CHECK (completion_source IS NULL OR completion_source IN ({$sources}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
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
