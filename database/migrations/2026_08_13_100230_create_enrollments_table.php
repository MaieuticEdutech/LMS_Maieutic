<?php

declare(strict_types=1);

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| enrollments — the single record of "who has access to what"
|--------------------------------------------------------------------------
|
| PHASE 3, TRACK C. architecture.md §6.4, §6.5, §12.2.
|
| TABLE CLASSIFICATION (planning.md rule S-5): TENANT-OWNED.
|
| DEPENDS ON `courses` and `lessons` (Track A, both already on `main`) and
| this track's own `orders` (`100200`, already migrated).
|
| THIS MIGRATION BUILDS THE TABLE ONLY. `EnrollmentAccessService` and
| `GrantEnrollment` — the code that gives this table meaning — are Govind's
| single-owner components (planning.md §21.3, TRACK-A-GOVIND.md). Nothing in
| this migration, the model, or the factory implements enrollment or access
| logic; they only make the storage correct.
|
| **`UNIQUE(user_id, course_id)`** is the single most load-bearing constraint
| in this track (architecture.md §6.5, Phase 3 DoD): it is what makes
| enrollment idempotent. A replayed payment webhook, or a retried admin
| grant, cannot create a second enrollment for the same student and course
| because the database refuses it — Phase 12's idempotency depends on this
| constraint existing, not merely on application-level care.
|
| `user_id` and `course_id` are NOT nullable and use RESTRICT: an enrollment
| must always name who and what, and deleting a user or course that has
| enrollment history must not silently erase it. `order_id`, `last_lesson_id`,
| `granted_by` and `revoked_by` are nullable and use SET NULL for the same
| audit-preserving reasoning as `audit_logs.user_id` and
| `assessments.created_by`.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->string('source');
            $table->string('status')->default(EnrollmentStatus::Active->value);

            $table->timestamp('enrolled_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revoke_reason')->nullable();

            // Rebuildable caches (lms:progress:rebuild), not truth — same
            // convention as courses.modules_count/lessons_count.
            $table->unsignedSmallInteger('progress_percentage')->default(0);
            $table->unsignedInteger('completed_lessons_count')->default(0);

            $table->foreignId('last_lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->timestamp('last_accessed_at')->nullable();

            $table->timestamps();

            // THE idempotency guarantee — see class docblock.
            $table->unique(['user_id', 'course_id']);

            $table->index(['course_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        // CHECK constraints mirroring the PHP enums (ADR-012).
        $sources = self::quoted(EnrollmentSource::values());
        $statuses = self::quoted(EnrollmentStatus::values());

        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_source_check CHECK (source IN ({$sources}))");
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_status_check CHECK (status IN ({$statuses}))");

        // A percentage is 0-100, not merely non-negative.
        DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollments_progress_percentage_range_check CHECK (progress_percentage BETWEEN 0 AND 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
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
