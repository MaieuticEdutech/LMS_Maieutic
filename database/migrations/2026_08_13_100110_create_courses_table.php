<?php

declare(strict_types=1);

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| courses
|--------------------------------------------------------------------------
|
| PHASE 3 · TRACK A. architecture.md §6.4. FR-CRS-01…11.
|
| TABLE CLASSIFICATION (rule S-5): TENANT-OWNED.
| `slug` is COMPOSITE-READY — becomes (organisation_id, slug) in V2.
|
| THREE COLUMNS CARRY DECISIONS, NOT JUST DATA:
|
|   price_amount — bigint in PAISE, with CHECK > 0 (ADR-007, ADR-014).
|       Integer minor units because floating point cannot represent decimal
|       currency exactly, and an LMS that mis-totals a payment by a paisa has
|       a reconciliation problem. CHECK > 0 because ALL V1 COURSES ARE PAID:
|       there is no free-course path, no zero-amount order, and the database
|       refuses to hold a course that would imply one.
|
|   status — only `published` appears in the public catalogue. Unpublishing
|       must never revoke access for already-enrolled students (FR-CRS-05),
|       which is why access is decided by `enrollments`, never by this column.
|
|   requires_final_test — whether course completion additionally requires
|       passing the final test (FR-ASMT-19, FR-PROG-11). Read in Phase 9.
|
| NO `is_free` COLUMN. Removed by business decision (ADR-014). Do not add it
| back "for later" — the CHECK constraint above would have to go with it, and
| that constraint is what makes "every course is paid" true rather than
| merely intended.
|
| Counter caches (modules_count, lessons_count, total_duration_seconds) are
| maintained in Phase 5 and rebuildable via `lms:counters:rebuild`. They are
| an optimisation, never the source of truth (P-8).
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();

            // A course outliving its category is fine; losing the course is not.
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();

            // Unordered display lists — no relational integrity needed, so
            // JSONB rather than two more tables (planning.md §8 rule 9).
            $table->jsonb('outcomes')->nullable();
            $table->jsonb('requirements')->nullable();

            $table->string('level')->default(CourseLevel::Beginner->value);
            $table->string('language')->default('en');

            $table->string('thumbnail_path')->nullable();
            $table->foreignId('promo_media_id')->nullable();

            // Money: integer paise. See class docblock.
            $table->bigInteger('price_amount');
            $table->char('currency', 3)->default('INR');

            $table->string('status')->default(CourseStatus::Draft->value);
            $table->timestamp('published_at')->nullable();

            $table->boolean('requires_final_test')->default(false);

            // The creating admin. RESTRICT, not cascade: deleting a user must
            // never delete the courses they authored.
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Rebuildable caches, not truth.
            $table->unsignedInteger('modules_count')->default(0);
            $table->unsignedInteger('lessons_count')->default(0);
            $table->unsignedInteger('total_duration_seconds')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['category_id', 'status']);
        });

        $statuses = self::quoted(CourseStatus::values());
        $levels = self::quoted(CourseLevel::values());

        DB::statement("ALTER TABLE courses ADD CONSTRAINT courses_status_check CHECK (status IN ({$statuses}))");
        DB::statement("ALTER TABLE courses ADD CONSTRAINT courses_level_check CHECK (level IN ({$levels}))");

        // ADR-014: all V1 courses are paid. The database, not just the
        // validator, refuses a course that would imply a free-enrollment path.
        DB::statement('ALTER TABLE courses ADD CONSTRAINT courses_price_positive_check CHECK (price_amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
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
