<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| attempt_answers — one student's answer to one question, in one attempt
|--------------------------------------------------------------------------
|
| PHASE 3, TRACK B. architecture.md §6.4. FR-ASMT-11.
|
| TABLE CLASSIFICATION (planning.md rule S-5): TENANT-OWNED.
|
| `attempt_id` CASCADES — explicitly specified (architecture.md §6.4, track
| brief: "ON DELETE CASCADE from ... attempt → answers"): an answer has no
| meaning without its attempt.
|
| `question_id` uses RESTRICT, not specified explicitly either way in
| architecture.md but chosen for the same reasoning already applied to
| `assessment_attempts.assessment_id`: an answer without the question it
| answered loses its meaning, so the question must not be deletable out from
| under a recorded answer.
|
| `is_correct` HERE IS NOT THE ANSWER KEY. `QuestionOption.is_correct` is the
| pre-submission answer key (NFR-SEC-21, AC-23, `$hidden` in that model) —
| this column is the GRADED RESULT of a specific submitted answer, produced
| after submission, and is exactly what a student's post-submission review
| screen is meant to show (subject to `AnswerRevealPolicy`). It is
| deliberately NOT hidden — hiding it would work against the reveal it is
| supposed to enable once the policy allows it.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->restrictOnDelete();

            $table->jsonb('selected_option_ids')->nullable();
            $table->text('answer_text')->nullable();

            $table->boolean('is_correct')->nullable();
            $table->decimal('marks_awarded', 6, 2)->nullable();

            $table->timestamp('answered_at');

            $table->timestamps();

            $table->unique(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_answers');
    }
};
