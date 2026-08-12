<?php

declare(strict_types=1);

use App\Enums\AttemptStatus;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Phase 3 (Track B) — assessment_attempts schema and model invariants
|--------------------------------------------------------------------------
|
| Guards the database-level protections the application relies on but never
| re-checks at runtime (architecture.md §6.4, §6.5, §10.2, ADR-012).
|
*/

it('creates the assessment_attempts table', function (): void {
    expect(Schema::hasTable('assessment_attempts'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| THE PARTIAL UNIQUE INDEX (FR-ASMT-16, AC-26) — the single most
| load-bearing constraint in this track. Proven by a test that expects the
| insert to throw, not merely by reading the migration.
|--------------------------------------------------------------------------
*/
it('refuses a second in-progress attempt for the same student and assessment', function (): void {
    $assessment = Assessment::factory()->create();
    $user = User::factory()->student()->create();

    AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->id,
        'user_id' => $user->id,
        'attempt_number' => 1,
    ]);

    expect(fn () => AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->id,
        'user_id' => $user->id,
        'attempt_number' => 2,
    ]))->toThrow(QueryException::class);
});

it('does not over-constrain: a new attempt is allowed once the prior one is no longer in-progress', function (AttemptStatus $priorStatus): void {
    $assessment = Assessment::factory()->create();
    $user = User::factory()->student()->create();

    AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->id,
        'user_id' => $user->id,
        'attempt_number' => 1,
        'status' => $priorStatus,
    ]);

    $second = AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->id,
        'user_id' => $user->id,
        'attempt_number' => 2,
    ]);

    expect($second->exists)->toBeTrue();
})->with([
    'submitted' => [AttemptStatus::Submitted],
    'graded' => [AttemptStatus::Graded],
    'expired' => [AttemptStatus::Expired],
    'abandoned' => [AttemptStatus::Abandoned],
]);

it('allows different students to each have an in-progress attempt on the same assessment', function (): void {
    $assessment = Assessment::factory()->create();

    AssessmentAttempt::factory()->create(['assessment_id' => $assessment->id]);
    AssessmentAttempt::factory()->create(['assessment_id' => $assessment->id]);

    expect(AssessmentAttempt::query()->where('assessment_id', $assessment->id)->where('status', AttemptStatus::InProgress)->count())
        ->toBe(2);
});

it('allows the same student an in-progress attempt on two different assessments', function (): void {
    $user = User::factory()->student()->create();

    AssessmentAttempt::factory()->create(['user_id' => $user->id]);
    AssessmentAttempt::factory()->create(['user_id' => $user->id]);

    expect(AssessmentAttempt::query()->where('user_id', $user->id)->where('status', AttemptStatus::InProgress)->count())
        ->toBe(2);
});

/*
| UNIQUE(assessment_id, user_id, attempt_number) — Phase 3 DoD, same
| test-not-read discipline.
*/
it('enforces unique attempt numbers per student per assessment', function (): void {
    $assessment = Assessment::factory()->create();
    $user = User::factory()->student()->create();

    AssessmentAttempt::factory()->submitted()->create([
        'assessment_id' => $assessment->id,
        'user_id' => $user->id,
        'attempt_number' => 1,
    ]);

    expect(fn () => AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->id,
        'user_id' => $user->id,
        'attempt_number' => 1,
    ]))->toThrow(QueryException::class);
});

/*
| CHECK CONSTRAINT (ADR-012).
*/
it('rejects an invalid status at the database level', function (): void {
    $attempt = AssessmentAttempt::factory()->create();

    expect(fn () => DB::table('assessment_attempts')->where('id', $attempt->id)->update(['status' => 'paused']))
        ->toThrow(QueryException::class);
});

it('accepts every attempt status the application can produce', function (AttemptStatus $status): void {
    $attempt = AssessmentAttempt::factory()->create();
    $attempt->forceFill(['status' => $status])->save();

    expect($attempt->refresh()->status)->toBe($status);
})->with('attempt statuses');

/*
| DELETE BEHAVIOUR — academic records survive; RESTRICT, not CASCADE
| (deliberately narrower than assessment → questions → options).
*/
it('refuses to delete an assessment that has attempts', function (): void {
    $assessment = Assessment::factory()->create();
    AssessmentAttempt::factory()->create(['assessment_id' => $assessment->id]);

    expect(fn () => $assessment->forceDelete())->toThrow(QueryException::class);
});

it('refuses to delete a user who has an attempt', function (): void {
    $user = User::factory()->student()->create();
    AssessmentAttempt::factory()->create(['user_id' => $user->id]);

    expect(fn () => $user->forceDelete())->toThrow(QueryException::class);
});

it('refuses to delete an enrollment that has an attempt', function (): void {
    $enrollment = Enrollment::factory()->create();
    AssessmentAttempt::factory()->create(['enrollment_id' => $enrollment->id]);

    // Enrollment has no SoftDeletes trait — delete() is already a hard delete.
    expect(fn () => $enrollment->delete())->toThrow(QueryException::class);
});

/*
| ULID — the URL-exposed handle, never the raw id (architecture.md §18).
*/
it('generates a unique ulid for every attempt', function (): void {
    $first = AssessmentAttempt::factory()->create();
    $second = AssessmentAttempt::factory()->create();

    expect($first->ulid)->not->toBe($second->ulid)
        ->and($first->getRouteKeyName())->toBe('ulid');
});

/*
| OWNERSHIP, LIFECYCLE AND GRADING FIELDS — never mass-assignable
| (NFR-SEC-21, NFR-SEC-07).
*/
it('refuses to mass-assign identity, lifecycle or grading fields', function (array $payload): void {
    expect(fn () => AssessmentAttempt::factory()->make()->fill($payload))
        ->toThrow(Exception::class);
})->with([
    'assessment_id' => [['assessment_id' => 1]],
    'user_id' => [['user_id' => 1]],
    'enrollment_id' => [['enrollment_id' => 1]],
    'status' => [['status' => AttemptStatus::Graded]],
    'attempt_number' => [['attempt_number' => 1]],
    'score_marks' => [['score_marks' => 10]],
    'is_passed' => [['is_passed' => true]],
    'question_order' => [['question_order' => [1, 2]]],
]);

/*
| RELATIONSHIP AND CASTS.
*/
it('lists an assessment\'s attempts through the owning relation', function (): void {
    $assessment = Assessment::factory()->create();
    AssessmentAttempt::factory()->count(2)->create(['assessment_id' => $assessment->id]);

    expect($assessment->attempts()->count())->toBe(2);
});

it('casts grading fields and question_order correctly', function (): void {
    $attempt = AssessmentAttempt::factory()->graded()->create(['question_order' => [3, 1, 2]]);

    expect($attempt->status)->toBe(AttemptStatus::Graded)
        ->and($attempt->is_passed)->toBeTrue()
        ->and($attempt->score_marks)->toBe('8.00')
        ->and($attempt->question_order)->toBe([3, 1, 2]);
});

dataset('attempt statuses', fn (): array => AttemptStatus::cases());
