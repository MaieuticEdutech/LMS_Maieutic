<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * TENANCY CLASSIFICATION: TENANT-OWNED.
 *
 * `users` is tenant-owned (see the table's own migration), so this column is
 * too.
 */
return new class extends Migration
{
    /**
     * How many minutes a week the learner wants to study ("Your week,
     * planned"). Defaults to 300 (5 hours). The CHECK keeps it between 30
     * minutes and 40 hours a week at the database level, so no code path can
     * store a nonsense goal. Not mass-assignable: set only through
     * App\Actions\Student\SetWeeklyGoal (NFR-SEC-07).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedSmallInteger('weekly_goal_minutes')->default(300);
        });

        DB::statement('ALTER TABLE users ADD CONSTRAINT users_weekly_goal_minutes_check CHECK (weekly_goal_minutes BETWEEN 30 AND 2400)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_weekly_goal_minutes_check');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('weekly_goal_minutes');
        });
    }
};
