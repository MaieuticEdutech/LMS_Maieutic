<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| instructor_profiles
|--------------------------------------------------------------------------
|
| PHASE 2. architecture.md §6.4.
|
| TABLE CLASSIFICATION (rule S-5): TENANT-OWNED — follows `users`.
|
| Kept separate from `users` so the identity table stays role-neutral. Adding
| headline/bio/expertise columns to `users` would leave them NULL for every
| student and super admin, and would grow the row that every authenticated
| request loads.
|
| ON DELETE CASCADE is correct here and only here: a profile has no meaning
| without its user, and carries no financial or audit significance. Financial
| references to users use RESTRICT/SET NULL instead.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('headline')->nullable();
            $table->text('bio')->nullable();

            // JSONB, not a related table: these are unordered, display-only
            // lists that nothing joins on or filters by (planning.md §8 rule 9).
            $table->jsonb('expertise')->nullable();
            $table->jsonb('links')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_profiles');
    }
};
