<?php

declare(strict_types=1);

use App\Models\Assessment;
use App\Models\Course;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| AssessmentPolicy (FR-ASMT-02, architecture.md §8.3)
|--------------------------------------------------------------------------
|
| Target shape: "Admin: all. Instructor: only on assigned courses. Student:
| none." The instructor branch was completed in Phase 10, once Course and
| `Course::assignedTo()`/`isAssignedTo()` existed to resolve "assigned" from.
|
*/

it('lets a super admin manage any assessment, denies a student, and gates the general abilities for an instructor', function (string $state, bool $general, bool $scoped): void {
    $actor = User::factory()->{$state}()->create();
    $assessment = Assessment::factory()->create();

    expect($actor->can('viewAny', Assessment::class))->toBe($general)
        ->and($actor->can('create', Assessment::class))->toBe($general)
        ->and($actor->can('view', $assessment))->toBe($scoped)
        ->and($actor->can('update', $assessment))->toBe($scoped)
        ->and($actor->can('delete', $assessment))->toBe($scoped)
        ->and($actor->can('publish', $assessment))->toBe($scoped);
})->with([
    // general: viewAny/create, no record to scope against yet.
    // scoped: view/update/delete/publish against an assessment attached to
    // a course this instructor is NOT assigned to (the factory default).
    'super admin' => ['superAdmin', true, true],
    'instructor' => ['instructor', true, false],
    'student' => ['student', false, false],
]);

it('lets an assigned instructor manage an assessment attached to their own course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();
    $course->instructors()->attach($instructor);

    $assessment = Assessment::factory()->create([
        'assessable_type' => Course::class,
        'assessable_id' => $course->getKey(),
    ]);

    expect($instructor->can('view', $assessment))->toBeTrue()
        ->and($instructor->can('update', $assessment))->toBeTrue()
        ->and($instructor->can('delete', $assessment))->toBeTrue()
        ->and($instructor->can('publish', $assessment))->toBeTrue();
});

it('denies an instructor an assessment attached to a course they are not assigned to', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();

    $assessment = Assessment::factory()->create([
        'assessable_type' => Course::class,
        'assessable_id' => $course->getKey(),
    ]);

    expect($instructor->can('view', $assessment))->toBeFalse()
        ->and($instructor->can('update', $assessment))->toBeFalse()
        ->and($instructor->can('delete', $assessment))->toBeFalse()
        ->and($instructor->can('publish', $assessment))->toBeFalse();
});
