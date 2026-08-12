<?php

declare(strict_types=1);

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| AttemptPolicy — architecture.md §8.3: "start / answer / submit / review |
| Owner only for write; instructor/admin read within scope."
|--------------------------------------------------------------------------
|
| `start()` takes an Assessment, not an AssessmentAttempt (there is no
| attempt yet when starting one). Calling `$user->can('start', $assessment)`
| directly would resolve AssessmentPolicy — the policy Laravel picks is
| determined by the ARGUMENT's class, not the ability name — so every call
| here uses the `[AssessmentAttempt::class, $assessment]` array form to
| force resolution to AttemptPolicy while still passing $assessment through
| as the actual argument. Phase 8 code calling `start` must do the same.
|
*/

it('lets a student start an attempt on a published assessment, and nobody else', function (): void {
    $student = User::factory()->student()->create();
    $admin = User::factory()->superAdmin()->create();
    $assessment = Assessment::factory()->create(['is_published' => true]);

    expect($student->can('start', [AssessmentAttempt::class, $assessment]))->toBeTrue()
        ->and($admin->can('start', [AssessmentAttempt::class, $assessment]))->toBeFalse();
});

it('refuses to start an attempt on an unpublished assessment', function (): void {
    $student = User::factory()->student()->create();
    $assessment = Assessment::factory()->unpublished()->create();

    expect($student->can('start', [AssessmentAttempt::class, $assessment]))->toBeFalse();
});

it('lets only the owning student answer or submit their attempt — write is owner-only, even for an admin', function (): void {
    $owner = User::factory()->student()->create();
    $admin = User::factory()->superAdmin()->create();
    $attempt = AssessmentAttempt::factory()->create(['user_id' => $owner->id]);

    expect($owner->can('answer', $attempt))->toBeTrue()
        ->and($owner->can('submit', $attempt))->toBeTrue()
        ->and($admin->can('answer', $attempt))->toBeFalse()
        ->and($admin->can('submit', $attempt))->toBeFalse();
});

it('refuses another student answering or submitting someone else\'s attempt', function (): void {
    $owner = User::factory()->student()->create();
    $other = User::factory()->student()->create();
    $attempt = AssessmentAttempt::factory()->create(['user_id' => $owner->id]);

    expect($other->can('answer', $attempt))->toBeFalse()
        ->and($other->can('submit', $attempt))->toBeFalse();
});

it('lets the owner and a super admin review an attempt', function (): void {
    $owner = User::factory()->student()->create();
    $admin = User::factory()->superAdmin()->create();
    $attempt = AssessmentAttempt::factory()->graded()->create(['user_id' => $owner->id]);

    expect($owner->can('review', $attempt))->toBeTrue()
        ->and($admin->can('review', $attempt))->toBeTrue();
});

it('refuses another student reviewing someone else\'s attempt', function (): void {
    $owner = User::factory()->student()->create();
    $other = User::factory()->student()->create();
    $attempt = AssessmentAttempt::factory()->graded()->create(['user_id' => $owner->id]);

    expect($other->can('review', $attempt))->toBeFalse();
});

it('denies an instructor reviewing an attempt — the deferred scope branch', function (): void {
    // Documented in AttemptPolicy and PROGRESS.md: resolving "the course
    // this attempt's assessment belongs to" through the polymorphic
    // assessable relation isn't built yet, so an instructor is denied
    // rather than guessed at. Not a bug — see the policy's docblock.
    $instructor = User::factory()->instructor()->create();
    $attempt = AssessmentAttempt::factory()->graded()->create();

    expect($instructor->can('review', $attempt))->toBeFalse();
});
