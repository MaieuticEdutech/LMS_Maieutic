<?php

declare(strict_types=1);

use App\Enums\AnswerRevealPolicy;
use App\Enums\AssessmentType;
use App\Enums\ScoringPolicy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| assessments — the quiz/test engine's single table
|--------------------------------------------------------------------------
|
| PHASE 3, TRACK B. architecture.md §6.4, ADR-002.
|
| TABLE CLASSIFICATION (planning.md rule S-5): TENANT-OWNED.
|
| No `quizzes` table and no `tests` table (ADR-002) — a quiz and a test are
| the same structure, differing only in what they attach to. `assessable`
| is a POLYMORPHIC relation to Lesson, Module or Course, so this migration
| has NO foreign key and no cross-track dependency: it does not wait on
| Track A's catalogue tables to exist.
|
| `total_marks` and `questions_count` are derived caches, recomputed when
| questions change (FR-ASMT-06) — never independently editable.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();

            // Polymorphic attach point: Lesson | Module | Course. No FK by
            // design — the referenced tables belong to Track A and B must
            // not wait on them (track brief, "nothing — start here").
            $table->morphs('assessable');

            $table->string('type');
            $table->string('title');
            $table->text('instructions')->nullable();

            $table->unsignedSmallInteger('passing_percentage');
            $table->unsignedInteger('time_limit_minutes')->nullable();
            $table->unsignedInteger('max_attempts')->nullable();
            $table->string('scoring_policy')->default(ScoringPolicy::Highest->value);

            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->string('answer_reveal');
            $table->boolean('negative_marking_enabled')->default(false);

            // Derived caches (FR-ASMT-06) — recomputed by the Question model's
            // save/delete hooks in Phase 8, never set directly by a form.
            $table->decimal('total_marks', 8, 2)->default(0);
            $table->unsignedInteger('questions_count')->default(0);

            $table->boolean('is_published')->default(false);
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();

            // Nullable + SET NULL: an assessment must not vanish because the
            // instructor who authored it was later removed from the system.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['type', 'is_published']);
        });

        // CHECK constraints mirroring the PHP enums (ADR-012).
        $types = self::quoted(AssessmentType::values());
        $scoringPolicies = self::quoted(ScoringPolicy::values());
        $answerRevealPolicies = self::quoted(AnswerRevealPolicy::values());

        DB::statement("ALTER TABLE assessments ADD CONSTRAINT assessments_type_check CHECK (type IN ({$types}))");
        DB::statement("ALTER TABLE assessments ADD CONSTRAINT assessments_scoring_policy_check CHECK (scoring_policy IN ({$scoringPolicies}))");
        DB::statement("ALTER TABLE assessments ADD CONSTRAINT assessments_answer_reveal_check CHECK (answer_reveal IN ({$answerRevealPolicies}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
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
