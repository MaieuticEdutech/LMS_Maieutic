<?php

declare(strict_types=1);

use App\Models\AssessmentAttempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Phase 3 (Track B) — attempt_answers schema and model invariants
|--------------------------------------------------------------------------
|
| Guards the database-level protections the application relies on but never
| re-checks at runtime (architecture.md §6.4, §6.5, ADR-012).
|
*/

it('creates the attempt_answers table', function (): void {
    expect(Schema::hasTable('attempt_answers'))->toBeTrue();
});

/*
| UNIQUE(attempt_id, question_id) — Phase 3 DoD, proven by a throw-test.
*/
it('enforces one answer per question per attempt', function (): void {
    $attempt = AssessmentAttempt::factory()->create();
    $question = Question::factory()->create();

    AttemptAnswer::factory()->create(['attempt_id' => $attempt->id, 'question_id' => $question->id]);

    expect(fn () => AttemptAnswer::factory()->create(['attempt_id' => $attempt->id, 'question_id' => $question->id]))
        ->toThrow(QueryException::class);
});

it('allows the same attempt to answer different questions', function (): void {
    $attempt = AssessmentAttempt::factory()->create();

    AttemptAnswer::factory()->create(['attempt_id' => $attempt->id]);
    AttemptAnswer::factory()->create(['attempt_id' => $attempt->id]);

    expect(AttemptAnswer::query()->where('attempt_id', $attempt->id)->count())->toBe(2);
});

/*
| DELETE BEHAVIOUR — attempt_id CASCADES (explicitly specified,
| architecture.md §6.4); question_id RESTRICTs (judgment call — see
| migration docblock).
*/
it('deletes its answers when the attempt is deleted', function (): void {
    // AssessmentAttempt has no SoftDeletes trait — delete() is already a
    // hard delete.
    $attempt = AssessmentAttempt::factory()->create();
    $answer = AttemptAnswer::factory()->create(['attempt_id' => $attempt->id]);

    $attempt->delete();

    expect(AttemptAnswer::query()->find($answer->id))->toBeNull();
});

it('refuses to delete a question that has a recorded answer', function (): void {
    $question = Question::factory()->create();
    AttemptAnswer::factory()->create(['question_id' => $question->id]);

    expect(fn () => $question->delete())->toThrow(QueryException::class);
});

/*
| OWNERSHIP AND GRADING FIELDS — never mass-assignable (NFR-SEC-07,
| NFR-SEC-21). Student-submitted content (selected_option_ids, answer_text,
| answered_at) IS fillable — the contrast is the point of this pair of tests.
*/
it('refuses to mass-assign identity or grading fields', function (array $payload): void {
    expect(fn () => AttemptAnswer::factory()->make()->fill($payload))
        ->toThrow(Exception::class);
})->with([
    'attempt_id' => [['attempt_id' => 1]],
    'question_id' => [['question_id' => 1]],
    'is_correct' => [['is_correct' => true]],
    'marks_awarded' => [['marks_awarded' => 5]],
]);

it('allows mass-assigning the student\'s own submitted content', function (): void {
    $answer = AttemptAnswer::factory()->make();

    expect(fn () => $answer->fill([
        'selected_option_ids' => [1, 2],
        'answer_text' => 'Paris',
        'answered_at' => now(),
    ]))->not->toThrow(Exception::class);
});

/*
|--------------------------------------------------------------------------
| is_correct IS NOT HIDDEN HERE — the deliberate contrast with
| QuestionOption::$hidden. This is the graded RESULT of a submitted answer,
| not the pre-submission answer key, and a review screen needs to show it.
|--------------------------------------------------------------------------
*/
it('includes is_correct in default serialisation, unlike the pre-submission answer key', function (): void {
    $answer = AttemptAnswer::factory()->graded(true, 5)->create();

    expect($answer->toArray())->toHaveKey('is_correct', true)
        ->and(json_decode($answer->toJson(), true, flags: JSON_THROW_ON_ERROR))->toHaveKey('is_correct', true);
});

/*
| RELATIONSHIP.
*/
it('lists an attempt\'s answers through the owning relation', function (): void {
    $attempt = AssessmentAttempt::factory()->create();
    AttemptAnswer::factory()->count(2)->create(['attempt_id' => $attempt->id]);

    expect($attempt->answers()->count())->toBe(2);
});

/*
| CASTS.
*/
it('casts selected_option_ids, is_correct, marks_awarded and answered_at correctly', function (): void {
    $answer = AttemptAnswer::factory()->shortAnswer()->graded(false, 0)->create();

    expect($answer->selected_option_ids)->toBeNull()
        ->and($answer->answer_text)->not->toBeNull()
        ->and($answer->is_correct)->toBeFalse()
        ->and($answer->marks_awarded)->toBe('0.00')
        ->and($answer->answered_at->isToday())->toBeTrue();
});
