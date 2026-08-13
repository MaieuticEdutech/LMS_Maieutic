<?php

declare(strict_types=1);

use App\Actions\Admin\AssignInstructorToCourse;
use App\Actions\Admin\CreateInstructor;
use App\Actions\Admin\UnassignInstructorFromCourse;
use App\Actions\Admin\UpdateInstructor;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Phase 4 Checkpoint 4 — instructor management Actions
|--------------------------------------------------------------------------
*/

it('creates an instructor with no password, pending activation, a linked profile, and sends the activation link', function (): void {
    Notification::fake();
    $admin = User::factory()->superAdmin()->create();

    $instructor = app(CreateInstructor::class)->handle([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'phone' => '9999999999',
        'headline' => 'Senior Backend Engineer',
        'bio' => 'Builds things.',
    ], $admin);

    // ->instructorProfile() (a query), not ->instructorProfile (the magic
    // property): CreateInstructor's HasOne::save() does not cache the
    // relation onto $instructor, so the magic property is still "unloaded"
    // here and `preventLazyLoading` would throw on a bare access.
    $profile = $instructor->instructorProfile()->first();

    expect($instructor->role)->toBe(UserRole::Instructor)
        ->and($instructor->status)->toBe(UserStatus::PendingActivation)
        ->and($instructor->password)->toBeNull()
        ->and($profile)->not->toBeNull()
        ->and($profile?->headline)->toBe('Senior Backend Engineer')
        ->and($profile?->bio)->toBe('Builds things.')
        ->and(AuditLog::query()->where('action', 'user.created')->where('auditable_id', $instructor->id)->exists())->toBeTrue();

    Notification::assertSentTo($instructor, App\Notifications\AccountActivationNotification::class);
});

it('creates an instructor with a profile even when headline and bio are omitted', function (): void {
    Notification::fake();
    $admin = User::factory()->superAdmin()->create();

    $instructor = app(CreateInstructor::class)->handle([
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
    ], $admin);

    $profile = $instructor->instructorProfile()->first();

    expect($profile)->not->toBeNull()
        ->and($profile?->headline)->toBeNull()
        ->and($profile?->bio)->toBeNull();
});

it('updates both the User fields and the InstructorProfile fields, audited as one entry', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $instructor = User::factory()->instructor()->create(['name' => 'Old Name', 'email' => 'old@example.com']);
    $instructor->instructorProfile()->create([
        'headline' => 'Old headline',
        'bio' => 'Old bio',
    ]);

    $updated = app(UpdateInstructor::class)->handle($instructor->refresh(), [
        'name' => 'New Name',
        'email' => 'new@example.com',
        'phone' => null,
        'headline' => 'New headline',
        'bio' => 'New bio',
    ], $admin);

    expect($updated->name)->toBe('New Name')
        ->and($updated->email)->toBe('new@example.com')
        ->and($updated->instructorProfile()->first()?->headline)->toBe('New headline')
        ->and($updated->instructorProfile()->first()?->bio)->toBe('New bio');

    $entry = AuditLog::query()->where('action', 'user.updated')->where('auditable_id', $instructor->id)->firstOrFail();
    $changes = $entry->changes ?? [];
    expect($changes['before']['name'])->toBe('Old Name')
        ->and($changes['after']['name'])->toBe('New Name')
        ->and($changes['before']['headline'])->toBe('Old headline')
        ->and($changes['after']['headline'])->toBe('New headline');
});

it('creates an InstructorProfile on update when the instructor did not have one yet', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $instructor = User::factory()->instructor()->create();

    expect($instructor->instructorProfile()->first())->toBeNull();

    $updated = app(UpdateInstructor::class)->handle($instructor, [
        'name' => $instructor->name,
        'email' => $instructor->email,
        'headline' => 'Brand new headline',
        'bio' => null,
    ], $admin);

    expect($updated->instructorProfile()->first()?->headline)->toBe('Brand new headline');
});

it('assigns an instructor to a course, sets assigned_by and assigned_at, and audits it', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();

    expect($course->isAssignedTo($instructor))->toBeFalse();

    app(AssignInstructorToCourse::class)->handle($course, $instructor, $admin);

    $course->refresh();
    expect($course->isAssignedTo($instructor))->toBeTrue();

    $pivot = DB::table('course_instructor')
        ->where('course_id', $course->id)
        ->where('user_id', $instructor->id)
        ->first();

    if (! $pivot instanceof stdClass) {
        throw new RuntimeException('Expected a course_instructor pivot row.');
    }

    expect($pivot->assigned_by)->toBe($admin->id)
        ->and($pivot->assigned_at)->not->toBeNull();

    expect(AuditLog::query()->where('action', 'course.instructor.assigned')->where('auditable_id', $course->id)->exists())->toBeTrue();
});

it('is idempotent: assigning the same instructor to the same course twice creates no duplicate row and no second audit entry', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();

    $action = app(AssignInstructorToCourse::class);
    $action->handle($course, $instructor, $admin);
    $action->handle($course, $instructor, $admin);

    $rowCount = DB::table('course_instructor')
        ->where('course_id', $course->id)
        ->where('user_id', $instructor->id)
        ->count();

    expect($rowCount)->toBe(1)
        ->and(AuditLog::query()->where('action', 'course.instructor.assigned')->where('auditable_id', $course->id)->count())->toBe(1);
});

it('unassigns an instructor from a course and audits it', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();
    app(AssignInstructorToCourse::class)->handle($course, $instructor, $admin);

    app(UnassignInstructorFromCourse::class)->handle($course->refresh(), $instructor, $admin);

    expect($course->refresh()->isAssignedTo($instructor))->toBeFalse()
        ->and(AuditLog::query()->where('action', 'course.instructor.unassigned')->where('auditable_id', $course->id)->exists())->toBeTrue();
});

it('unassigning an instructor from a course does NOT delete or touch any Assessment they authored on that course', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->create();
    app(AssignInstructorToCourse::class)->handle($course, $instructor, $admin);

    $assessment = Assessment::factory()->forCourse()->create([
        'assessable_id' => $course->id,
        'created_by' => $instructor->id,
        'title' => 'Final Test',
    ]);

    app(UnassignInstructorFromCourse::class)->handle($course->refresh(), $instructor, $admin);

    expect($course->refresh()->isAssignedTo($instructor))->toBeFalse()
        ->and(Assessment::query()->find($assessment->id))->not->toBeNull()
        ->and(Assessment::query()->find($assessment->id)?->title)->toBe('Final Test')
        ->and(Assessment::query()->find($assessment->id)?->created_by)->toBe($instructor->id);
});
