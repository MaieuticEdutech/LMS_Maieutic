<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| modules — the middle tier of Course → Module → Lesson
|--------------------------------------------------------------------------
|
| PHASE 3 · TRACK A. architecture.md §6.4, §9.1. FR-CNT-01, FR-CNT-03, FR-CNT-05.
|
| TABLE CLASSIFICATION (rule S-5): TENANT-OWNED.
|
| CASCADE on course_id is deliberate and safe: a module has no meaning outside
| its course, and deleting a course is already a soft delete at the model
| layer (FR-CRS-06), so this cascade only fires on a genuine hard delete.
|
| `position` is an explicit integer, not an implicit id ordering. The Course
| Builder reorders by drag-and-drop (FR-CNT-03), and reordering must be
| transactional with no duplicate positions (FR-CNT-04) — which is only
| expressible with a column you control.
|
| `is_published` is separate from the course's own status: a module may be
| drafted inside a published course and stay invisible to students
| (FR-CNT-05).
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_published')->default(false);

            // Rebuildable cache, not truth (P-8).
            $table->unsignedInteger('lessons_count')->default(0);

            $table->timestamps();

            // The curriculum read path: ordered modules for one course.
            $table->index(['course_id', 'position']);

            // The student read path: only published modules.
            $table->index(['course_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
