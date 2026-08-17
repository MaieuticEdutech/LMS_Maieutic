<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| course_reviews  (+ two cached counters on courses)
|--------------------------------------------------------------------------
|
| TRACK A. Design handoff §2 — the "★ 4.8" on every course card and the RATING
| facet in the catalogue rail.
|
| TABLE CLASSIFICATION (rule S-5): TENANT-OWNED.
|
| THREE DECISIONS ARE IN THIS SCHEMA, NOT JUST DATA:
|
|   enrollment_id — UNIQUE, and the reason reviews hang off the ENROLMENT
|       rather than off (user_id, course_id). Only someone who was given access
|       may rate a course, and the enrolment is the record of that. A unique
|       index on it makes "one review per enrolment" a database fact rather
|       than something the Action has to remember, and it survives two tabs
|       submitting at once — which a check-then-insert does not.
|
|   rating — smallint with a CHECK between 1 and 5 (ADR-012). Every status and
|       range in this schema is constrained at the database, because an
|       application bug that writes a 7 must fail loudly rather than quietly
|       skewing an average that appears on a sales page.
|
|   rating_sum / rating_count on `courses` — NOT an average column.
|       ═══════════════════════════════════════════════════════════════════
|       Storing the mean would mean storing a float, and this project does not
|       keep money in floats for exactly the reason it should not keep an
|       average in one: the value drifts as it is recomputed, and two courses
|       that received identical ratings could end up displaying differently.
|
|       Sum and count are integers. They are exact, they update with a single
|       atomic increment when a review lands, and the mean is derived on read.
|       The same trick as `Money` keeping paise: store what is countable and
|       compute what is not.
|       ═══════════════════════════════════════════════════════════════════
|
|       They are counter caches and therefore never the source of truth —
|       `course_reviews` is. Rebuildable by summing the table.
|
| NO `is_approved` / MODERATION COLUMN. Moderation is a real need eventually,
| but it needs a queue, an actor and a policy to mean anything. A bare boolean
| now would invite ->where('is_approved', true) to spread through the codebase
| before any of that exists, and every existing row would have to be
| back-filled with an answer nobody had actually given.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_reviews', function (Blueprint $table) {
            $table->id();

            /*
             * The enrolment is what earns the right to review. user_id and
             * course_id are denormalised alongside it so the catalogue can list
             * and aggregate without joining through enrollments.
             */
            $table->foreignId('enrollment_id')
                ->unique()
                ->constrained('enrollments')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnDelete();

            $table->smallInteger('rating');

            // Optional. A star with no words is still a rating, and demanding
            // prose is how you end up with far fewer of both.
            $table->text('body')->nullable();

            $table->timestamps();

            // The course's own review list, newest first.
            $table->index(['course_id', 'created_at']);
        });

        DB::statement('ALTER TABLE course_reviews ADD CONSTRAINT course_reviews_rating_check CHECK (rating BETWEEN 1 AND 5)');

        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedInteger('rating_sum')->default(0);
            $table->unsignedInteger('rating_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['rating_sum', 'rating_count']);
        });

        Schema::dropIfExists('course_reviews');
    }
};
