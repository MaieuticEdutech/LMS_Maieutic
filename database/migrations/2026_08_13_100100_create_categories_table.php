<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| categories — catalogue grouping
|--------------------------------------------------------------------------
|
| PHASE 3 · TRACK A. architecture.md §6.4. FR-CRS-07.
|
| TABLE CLASSIFICATION (planning.md rule S-5): TENANT-OWNED.
| `slug` is COMPOSITE-READY: in V2 the unique index becomes
| (organisation_id, slug), so two organisations may each have a "Design"
| category without collision (architecture.md §24.2).
|
| Self-referencing parent_id supports a shallow hierarchy for browsing. The
| FK is nullOnDelete rather than cascade: deleting a parent category must
| orphan its children to the top level, never silently delete a subtree of
| categories that courses still point at.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Explicit ordering for the catalogue menu. Never rely on id order.
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['parent_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
