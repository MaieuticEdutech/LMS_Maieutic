<?php

declare(strict_types=1);

use App\Models\Assessment;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Phase 3 (Track B) — question_options schema, model and answer-key secrecy
|--------------------------------------------------------------------------
|
| Guards the database-level protections the application relies on but never
| re-checks at runtime (architecture.md §6.4, §6.5, ADR-012), plus the
| answer-key secrecy property this table exists to protect (NFR-SEC-21,
| AC-23).
|
*/

it('creates the question_options table', function (): void {
    expect(Schema::hasTable('question_options'))->toBeTrue();
});

/*
| CASCADE (Phase 3 DoD — "deleting an assessment cascades to questions and
| options").
*/
it('deletes its options when the parent question is deleted', function (): void {
    $question = Question::factory()->create();
    $option = QuestionOption::factory()->create(['question_id' => $question->id]);

    $question->delete();

    expect(QuestionOption::query()->find($option->id))->toBeNull();
});

it('deletes its options when the assessment is deleted, through the question', function (): void {
    $assessment = Assessment::factory()->create();
    $question = Question::factory()->create(['assessment_id' => $assessment->id]);
    $option = QuestionOption::factory()->create(['question_id' => $question->id]);

    $assessment->delete();

    expect(QuestionOption::query()->find($option->id))->toBeNull();
});

/*
| OWNERSHIP FIELD — the same convention as Question::assessment_id and
| InstructorProfile::user_id: the parent key is set through the owning
| relation, not mass-assigned.
*/
it('refuses to mass-assign question_id', function (): void {
    expect(fn () => QuestionOption::factory()->make()->fill(['question_id' => 1]))
        ->toThrow(Exception::class);
});

/*
| RELATIONSHIP AND ORDERING.
*/
it('orders a question\'s options by position', function (): void {
    $question = Question::factory()->create();
    $third = QuestionOption::factory()->create(['question_id' => $question->id, 'position' => 2]);
    $first = QuestionOption::factory()->create(['question_id' => $question->id, 'position' => 0]);
    $second = QuestionOption::factory()->create(['question_id' => $question->id, 'position' => 1]);

    expect($question->options()->pluck('id')->all())
        ->toBe([$first->id, $second->id, $third->id]);
});

/*
| CASTS.
*/
it('casts is_correct and position correctly', function (): void {
    $option = QuestionOption::factory()->correct()->create(['position' => 3]);

    expect($option->is_correct)->toBeTrue()
        ->and($option->position)->toBe(3);
});

/*
|--------------------------------------------------------------------------
| THE ANSWER KEY MUST NEVER LEAK THROUGH DEFAULT SERIALISATION (AC-23)
|--------------------------------------------------------------------------
|
| This is deliberately NOT a test of "the path we intended" — Phase 3 ships
| no API resource, view or presenter at all (no user-facing feature work
| this phase), so there is no intended path yet to test. Instead this proves
| a NEGATIVE about the raw model itself: is_correct is absent from
| toArray()/toJson() — including when nested under its parent question, the
| exact shape a careless `return $question->load('options');` would produce
| in some future controller. A developer who deletes QuestionOption::$hidden
| or forgets it while adding a new endpoint fails THIS test, not a bespoke
| resource test that only proves the resource they remembered to write is
| correct.
|
| The final assertions confirm $hidden did not also break legitimate access:
| the value is still directly readable, which is what grading logic and the
| Phase 8 QuestionPresenter need.
|
*/
it('never includes is_correct in default array or JSON serialisation, even nested', function (): void {
    $question = Question::factory()->create();
    $correctOption = QuestionOption::factory()->correct()->create(['question_id' => $question->id]);
    QuestionOption::factory()->create(['question_id' => $question->id]);

    expect($correctOption->toArray())->not->toHaveKey('is_correct')
        ->and(json_decode($correctOption->toJson(), true, flags: JSON_THROW_ON_ERROR))->not->toHaveKey('is_correct');

    $nestedOptions = $question->load('options')->toArray()['options'];

    expect($nestedOptions)->not->toBeEmpty();
    foreach ($nestedOptions as $serialisedOption) {
        expect($serialisedOption)->not->toHaveKey('is_correct');
    }

    // Hidden from serialisation, not gone: direct access still works, which
    // is what internal grading logic and any future authorised presenter
    // depend on.
    expect($correctOption->is_correct)->toBeTrue()
        ->and($correctOption->getAttribute('is_correct'))->toBeTrue();
});

it('still stores false for an incorrect option, readable directly, despite being hidden', function (): void {
    $option = QuestionOption::factory()->create(['is_correct' => false]);

    expect($option->toArray())->not->toHaveKey('is_correct')
        ->and($option->is_correct)->toBeFalse();
});
