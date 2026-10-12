<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| enrollments.enrolled_at — index for the storefront's "Top 10 this week"
|--------------------------------------------------------------------------
|
| TABLE CLASSIFICATION (rule S-5): TENANT-OWNED, same as the table itself.
|
| StorefrontService::topThisWeek() and the Bestseller badge both count
| enrolments in a recent window ("enrolled_at >= now() - 7 days"). Without an
| index that is a full scan of a table that only grows.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->index(['enrolled_at', 'course_id'], 'enrollments_enrolled_at_course_index');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropIndex('enrollments_enrolled_at_course_index');
        });
    }
};
