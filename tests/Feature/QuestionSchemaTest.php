<?php

declare(strict_types=1);

use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\Question;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Phase 3 (Track B) — questions schema and model invariants
|--------------------------------------------------------------------------
|
| Guards the database-level protections the application relies on but never
| re-checks at runtime (architecture.md §6.4, §6.5, ADR-012).
|
*/

it('creates the questions table', function (): void {
    expect(Schema::hasTable('questions'))->toBeTrue();
});

/*
| CHECK CONSTRAINT (ADR-012). The database refuses an illegal type even if
| application code is bypassed.
*/
it('rejects an invalid type at the database level', function (): void {
    $question = Question::factory()->create();

    expect(fn () => DB::table('questions')->where('id', $question->id)->update(['type' => 'essay']))
        ->toThrow(QueryException::class);
});

it('accepts every question type the application can produce', function (QuestionType $type): void {
    $question = Question::factory()->create();
    $question->forceFill(['type' => $type])->save();

    expect($question->refresh()->type)->toBe($type);
})->with('question types');

/*
| CASCADE (Phase 3 DoD — "deleting an assessment cascades to questions").
*/
it('deletes its questions when the parent assessment is deleted', function (): void {
    $assessment = Assessment::factory()->create();
    $question = Question::factory()->create(['assessment_id' => $assessment->id]);

    $assessment->delete();

    expect(Question::query()->find($question->id))->toBeNull();
});

/*
| OWNERSHIP FIELD — the same convention as InstructorProfile::user_id: the
| parent key is set through the owning relation, not mass-assigned.
*/
it('refuses to mass-assign assessment_id', function (): void {
    expect(fn () => Question::factory()->make()->fill(['assessment_id' => 1]))
        ->toThrow(Exception::class);
});

/*
| RELATIONSHIP AND ORDERING.
*/
it('orders an assessment\'s questions by position', function (): void {
    $assessment = Assessment::factory()->create();
    $third = Question::factory()->create(['assessment_id' => $assessment->id, 'position' => 2]);
    $first = Question::factory()->create(['assessment_id' => $assessment->id, 'position' => 0]);
    $second = Question::factory()->create(['assessment_id' => $assessment->id, 'position' => 1]);

    expect($assessment->questions()->pluck('id')->all())
        ->toBe([$first->id, $second->id, $third->id]);
});

/*
| CASTS.
*/
it('casts marks, negative marks and meta correctly', function (): void {
    $question = Question::factory()->shortAnswer()->create([
        'marks' => 2.5,
        'negative_marks' => 0.5,
    ]);

    expect($question->marks)->toBe('2.50')
        ->and($question->negative_marks)->toBe('0.50')
        ->and($question->meta)->toBeArray()
        ->and($question->meta)->toHaveKey('accepted_answers');
});

dataset('question types', fn (): array => QuestionType::cases());
