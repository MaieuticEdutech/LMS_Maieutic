<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| question_options — the answer key lives here
|--------------------------------------------------------------------------
|
| PHASE 3, TRACK B. architecture.md §6.4.
|
| TABLE CLASSIFICATION (planning.md rule S-5): TENANT-OWNED.
|
| ON DELETE CASCADE from questions: an option has no meaning without its
| question (Phase 3 DoD — "deleting an assessment cascades to questions and
| options").
|
| `is_correct` IS THE ANSWER KEY (NFR-SEC-21, AC-23). It must never be
| serialised to a student before submission. That policy-aware reveal is a
| Phase 8 concern (a dedicated `QuestionPresenter` — already named in
| architecture.md §6.4 and §12.2, not invented here); this migration's job is
| only to build the column correctly. See QuestionOption's model docblock and
| PROGRESS.md for the layered defence this track puts in place now.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('question_id')->constrained()->cascadeOnDelete();

            $table->text('body');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['question_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
    }
};
