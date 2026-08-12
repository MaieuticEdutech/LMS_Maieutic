<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| EnrollmentPolicy — record visibility and management, NOT the access gate
|--------------------------------------------------------------------------
|
| architecture.md §8.3: "view / grant / revoke | Admin only for write;
| student reads own; instructor reads within assigned course."
|
*/

it('lets a super admin view, grant and revoke any enrollment', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $enrollment = Enrollment::factory()->create();

    expect($admin->can('viewAny', Enrollment::class))->toBeTrue()
        ->and($admin->can('view', $enrollment))->toBeTrue()
        ->and($admin->can('grant', Enrollment::class))->toBeTrue()
        ->and($admin->can('revoke', $enrollment))->toBeTrue();
});

it('lets a student view only their own enrollment, and never grant or revoke', function (): void {
    $student = User::factory()->student()->create();
    $own = Enrollment::factory()->create(['user_id' => $student->id]);
    $someoneElses = Enrollment::factory()->create();

    expect($student->can('view', $own))->toBeTrue()
        ->and($student->can('view', $someoneElses))->toBeFalse()
        ->and($student->can('grant', Enrollment::class))->toBeFalse()
        ->and($student->can('revoke', $own))->toBeFalse();
});

it('lets an instructor view an enrollment only within an assigned course, and never grant or revoke', function (): void {
    $instructor = User::factory()->instructor()->create();
    $assignedCourse = Course::factory()->create();
    $assignedCourse->instructors()->attach($instructor);

    $withinScope = Enrollment::factory()->create(['course_id' => $assignedCourse->id]);
    $outsideScope = Enrollment::factory()->create();

    expect($instructor->can('view', $withinScope))->toBeTrue()
        ->and($instructor->can('view', $outsideScope))->toBeFalse()
        ->and($instructor->can('grant', Enrollment::class))->toBeFalse()
        ->and($instructor->can('revoke', $withinScope))->toBeFalse();
});
