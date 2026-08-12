<?php

declare(strict_types=1);

use App\Enums\AttemptStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| assessment_attempts — one student's attempt at an assessment
|--------------------------------------------------------------------------
|
| PHASE 3, TRACK B. architecture.md §6.4, §6.5, §10.2. FR-ASMT-16, AC-26.
|
| TABLE CLASSIFICATION (planning.md rule S-5): TENANT-OWNED.
|
| Was blocked on Track C's `enrollments` (`100230`); unblocked and merged in
| the same session `enrollments` was built. `assessment_id` (`100300`),
| `user_id` and `enrollment_id` (`100230`) are all real FKs now.
|
| THE PARTIAL UNIQUE INDEX BELOW IS THE SINGLE MOST LOAD-BEARING CONSTRAINT
| IN THIS TRACK (track brief, architecture.md §6.5): it enforces "a student
| cannot have two simultaneous in_progress attempts on the same assessment"
| (FR-ASMT-16, AC-26) at the database level. Application-level checks race;
| this does not — two concurrent StartAttempt requests cannot both succeed,
| because only one row satisfying the partial index's WHERE clause can ever
| exist. This is also why the test suite runs on real PostgreSQL: SQLite has
| no partial indexes, so a green SQLite suite would prove nothing here.
|
| `assessment_id` uses RESTRICT, not CASCADE — deliberately narrower than the
| assessment → questions → options cascade chain. The Phase 3 DoD checklist
| scopes the cascade requirement to "questions and options" only; a deleted
| assessment must not retroactively erase a student's grading history
| (same reasoning as financial-record FKs never cascading). `user_id` and
| `enrollment_id` RESTRICT for the same reason — an attempt is an academic
| record, not disposable data.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_attempts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();

            $table->foreignId('assessment_id')->constrained('assessments')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->restrictOnDelete();

            $table->unsignedInteger('attempt_number');
            $table->string('status')->default(AttemptStatus::InProgress->value);

            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();

            $table->decimal('score_marks', 8, 2)->nullable();
            $table->decimal('max_marks', 8, 2)->nullable();
            $table->decimal('score_percentage', 5, 2)->nullable();
            $table->boolean('is_passed')->nullable();

            $table->unsignedInteger('time_spent_seconds')->default(0);

            // Snapshotted per attempt (FR-ASMT-18) so a review shows what
            // the student actually saw, even after shuffling or a later
            // curriculum edit.
            $table->jsonb('question_order');

            $table->timestamps();

            $table->unique(['assessment_id', 'user_id', 'attempt_number']);
            $table->index(['user_id', 'status']);
        });

        // CHECK constraint mirroring the PHP enum (ADR-012).
        $statuses = self::quoted(AttemptStatus::values());

        DB::statement("ALTER TABLE assessment_attempts ADD CONSTRAINT assessment_attempts_status_check CHECK (status IN ({$statuses}))");

        // THE partial unique index — FR-ASMT-16, AC-26. See class docblock.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX assessment_attempts_one_in_progress
                ON assessment_attempts (assessment_id, user_id)
                WHERE status = 'in_progress'
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_attempts');
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
