<?php

declare(strict_types=1);

use App\Enums\AnswerRevealPolicy;
use App\Enums\AssessmentType;
use App\Enums\ScoringPolicy;
use App\Models\Assessment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Phase 3 (Track B) — assessments schema and model invariants
|--------------------------------------------------------------------------
|
| Guards the database-level protections the application relies on but never
| re-checks at runtime (architecture.md §6.4, §6.5, ADR-012).
|
*/

it('creates the assessments table', function (): void {
    expect(Schema::hasTable('assessments'))->toBeTrue();
});

it('has no foreign key on the polymorphic assessable relation', function (): void {
    // ADR-002 / track brief: assessments attaches to Lesson, Module or Course
    // polymorphically, so it must accept an assessable_id that matches no row
    // in any table — there is nothing to enforce referential integrity
    // against, by design, so Track B never waits on Track A's tables.
    $assessment = Assessment::factory()->create([
        'assessable_type' => 'App\\Models\\Course',
        'assessable_id' => 999_999_999,
    ]);

    expect($assessment->refresh()->assessable_id)->toBe(999_999_999);
});

/*
| CHECK CONSTRAINTS (ADR-012). The database refuses an illegal type, scoring
| policy or answer-reveal policy even if application code is bypassed.
*/
it('rejects an invalid type at the database level', function (): void {
    $assessment = Assessment::factory()->create();

    expect(fn () => DB::table('assessments')->where('id', $assessment->id)->update(['type' => 'survey']))
        ->toThrow(QueryException::class);
});

it('rejects an invalid scoring policy at the database level', function (): void {
    $assessment = Assessment::factory()->create();

    expect(fn () => DB::table('assessments')->where('id', $assessment->id)->update(['scoring_policy' => 'average']))
        ->toThrow(QueryException::class);
});

it('rejects an invalid answer reveal policy at the database level', function (): void {
    $assessment = Assessment::factory()->create();

    expect(fn () => DB::table('assessments')->where('id', $assessment->id)->update(['answer_reveal' => 'always']))
        ->toThrow(QueryException::class);
});

it('accepts every type, scoring policy and answer reveal combination the application can produce', function (): void {
    foreach (AssessmentType::cases() as $type) {
        foreach (ScoringPolicy::cases() as $scoringPolicy) {
            foreach (AnswerRevealPolicy::cases() as $answerReveal) {
                $assessment = Assessment::factory()->create();
                $assessment->forceFill([
                    'type' => $type,
                    'scoring_policy' => $scoringPolicy,
                    'answer_reveal' => $answerReveal,
                ])->save();
                $assessment->refresh();

                expect($assessment->type)->toBe($type)
                    ->and($assessment->scoring_policy)->toBe($scoringPolicy)
                    ->and($assessment->answer_reveal)->toBe($answerReveal);
            }
        }
    }
});

/*
| OWNERSHIP FIELD (planning.md conventions — ownership fields never fillable).
*/
it('refuses to mass-assign created_by', function (): void {
    expect(fn () => Assessment::factory()->make()->fill(['created_by' => 1]))
        ->toThrow(Exception::class);
});

it('keeps an assessment when its creator is deleted', function (): void {
    $instructor = User::factory()->instructor()->create();
    $assessment = Assessment::factory()->create(['created_by' => $instructor->id]);

    $instructor->forceDelete();

    $survivor = Assessment::query()->find($assessment->id);

    expect($survivor)->not->toBeNull()
        ->and($survivor?->created_by)->toBeNull();
});

/*
| SCOPE.
*/
it('scopes to published assessments only', function (): void {
    Assessment::factory()->count(2)->create(['is_published' => true]);
    Assessment::factory()->unpublished()->create();

    expect(Assessment::query()->published()->count())->toBe(2);
});

it('casts enum and boolean attributes correctly', function (): void {
    $assessment = Assessment::factory()->timed(45)->create([
        'type' => AssessmentType::Test,
        'shuffle_questions' => true,
    ]);

    expect($assessment->type)->toBe(AssessmentType::Test)
        ->and($assessment->scoring_policy)->toBe(ScoringPolicy::Highest)
        ->and($assessment->answer_reveal)->toBe(AnswerRevealPolicy::AfterSubmit)
        ->and($assessment->shuffle_questions)->toBeTrue()
        ->and($assessment->time_limit_minutes)->toBe(45);
});
