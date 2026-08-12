<?php

declare(strict_types=1);

use App\Models\Assessment;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| AssessmentPolicy — Phase 3 baseline (FR-ASMT-02, architecture.md §8.3)
|--------------------------------------------------------------------------
|
| Target shape is "Admin: all. Instructor: only on assigned courses.
| Student: none." The instructor branch is not yet wired — Track A's Course
| model does not exist on main yet, so "assigned courses" cannot be resolved.
| Until it lands, deny-by-default means an instructor is refused every
| action, which this test locks in as the current, correct behaviour.
|
*/

it('lets only a super admin manage assessments today', function (string $state, bool $allowed): void {
    $actor = User::factory()->{$state}()->create();
    $assessment = Assessment::factory()->create();

    expect($actor->can('viewAny', Assessment::class))->toBe($allowed)
        ->and($actor->can('view', $assessment))->toBe($allowed)
        ->and($actor->can('create', Assessment::class))->toBe($allowed)
        ->and($actor->can('update', $assessment))->toBe($allowed)
        ->and($actor->can('delete', $assessment))->toBe($allowed)
        ->and($actor->can('publish', $assessment))->toBe($allowed);
})->with([
    'super admin' => ['superAdmin', true],
    'instructor' => ['instructor', false],
    'student' => ['student', false],
]);
