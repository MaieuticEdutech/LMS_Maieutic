<?php

declare(strict_types=1);

use App\Enums\CourseInstructorRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| course_instructor — THE BASIS OF ALL INSTRUCTOR AUTHORISATION
|--------------------------------------------------------------------------
|
| PHASE 3 · TRACK A. architecture.md §6.4, §8.4. FR-INS-09, FR-RBAC-04, AC-03.
|
| TABLE CLASSIFICATION (rule S-5): TENANT-OWNED.
|
| WHY THIS SMALL PIVOT MATTERS MORE THAN ITS SIZE SUGGESTS:
|
| Instructor authorisation reduces to exactly one question — "is this course
| in course_instructor for this user?" This table IS that answer. Every
| instructor query in Phase 10 begins at Course::assignedTo($user), which
| reads this pivot, so scope leakage requires actively bypassing the standard
| entry point rather than merely forgetting a WHERE clause.
|
| UNIQUE(course_id, user_id) prevents a double assignment, which would
| otherwise duplicate every row of every instructor-scoped listing.
|
| CASCADE on both FKs is correct HERE and only here: an assignment has no
| meaning without either side, and carries no financial or audit weight. But
| note FR-INS-12 — unassigning an instructor must NOT delete the assessments
| they authored. Those live in Track B's tables and reference `users`
| directly, so deleting this row is genuinely safe.
|
| `assigned_by` records WHO granted the assignment. It is nullOnDelete rather
| than cascade: losing the granting admin must not silently revoke a working
| instructor's access.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_instructor', function (Blueprint $table) {
            $table->id();

            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('role_in_course')->default(CourseInstructorRole::Lead->value);

            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('assigned_at')->nullable();

            $table->timestamps();

            // One assignment per instructor per course.
            $table->unique(['course_id', 'user_id']);

            // The access path Phase 10 reads on every request: "which courses
            // is this user assigned to?"
            $table->index(['user_id', 'course_id']);
        });

        $roles = self::quoted(CourseInstructorRole::values());

        DB::statement(
            "ALTER TABLE course_instructor ADD CONSTRAINT course_instructor_role_check CHECK (role_in_course IN ({$roles}))",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('course_instructor');
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
