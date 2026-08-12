<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Lesson;
use App\Models\MediaFile;
use App\Models\Module;
use App\Models\User;
use App\Services\Enrollment\EnrollmentAccessService;

/*
|--------------------------------------------------------------------------
| Phase 3 · Track A — authorisation
|--------------------------------------------------------------------------
|
| FR-RBAC-04, FR-INS-08, FR-CNT-05, AC-01, AC-02, AC-03.
|
| The two questions these tests keep separate:
|   view()   — may this user see the course/lesson EXISTS? (the shop window)
|   access() — may they open its CONTENT? (the paid product)
|
| Conflating them is how a catalogue page leaks a lesson body.
|
*/

/*
| THE SHOP WINDOW — published metadata is public (FR-STU-04).
*/
it('lets anyone view a published course', function (): void {
    $course = Course::factory()->published()->create();

    expect(app(App\Policies\CoursePolicy::class)->view(null, $course))->toBeTrue();
});

it('hides a draft course from guests and students', function (): void {
    $course = Course::factory()->create(); // draft
    $policy = app(App\Policies\CoursePolicy::class);

    expect($policy->view(null, $course))->toBeFalse()
        ->and($policy->view(User::factory()->student()->create(), $course))->toBeFalse();
});

it('shows a draft course to an admin and to its assigned instructor', function (): void {
    $course = Course::factory()->create();
    $instructor = User::factory()->instructor()->create();
    $course->instructors()->attach($instructor);

    $policy = app(App\Policies\CoursePolicy::class);

    expect($policy->view(User::factory()->superAdmin()->create(), $course))->toBeTrue()
        ->and($policy->view($instructor, $course))->toBeTrue()
        // An UNASSIGNED instructor gets nothing (AC-03).
        ->and($policy->view(User::factory()->instructor()->create(), $course))->toBeFalse();
});

/*
| THE PAID PRODUCT — access requires more than existence.
|
| This is the central assertion of the whole project: a student account is not
| course access (FR-ENR-01). Even on a PUBLISHED course, a student without an
| enrollment gets nothing.
*/
it('denies content access to a student with no enrollment', function (): void {
    $course = Course::factory()->published()->create();
    $student = User::factory()->student()->create();

    expect(app(EnrollmentAccessService::class)->grantsAccess($student, $course))->toBeFalse();
});

it('grants content access to a super admin', function (): void {
    $course = Course::factory()->create();
    $admin = User::factory()->superAdmin()->create();

    expect(app(EnrollmentAccessService::class)->grantsAccess($admin, $course))->toBeTrue();
});

it('grants content access to an ASSIGNED instructor only', function (): void {
    $course = Course::factory()->create();
    $assigned = User::factory()->instructor()->create();
    $unassigned = User::factory()->instructor()->create();

    $course->instructors()->attach($assigned);

    $access = app(EnrollmentAccessService::class);

    expect($access->grantsAccess($assigned, $course))->toBeTrue()
        // Being an instructor grants nothing by itself (FR-RBAC-04, AC-03).
        ->and($access->grantsAccess($unassigned, $course))->toBeFalse();
});

it('denies access to a suspended user even when otherwise entitled', function (): void {
    // Checked before role, so a suspended admin or assigned instructor is
    // still refused.
    $course = Course::factory()->create();
    $admin = User::factory()->superAdmin()->suspended()->create();
    $instructor = User::factory()->instructor()->suspended()->create();
    $course->instructors()->attach($instructor);

    $access = app(EnrollmentAccessService::class);

    expect($access->grantsAccess($admin, $course))->toBeFalse()
        ->and($access->grantsAccess($instructor, $course))->toBeFalse();
});

/*
| PHASE 3 STATE — documented, not accidental.
|
| The student branch returns false until Phase 6 wires the enrollment lookup.
| This test asserts the FAIL-SAFE DIRECTION: if Phase 6 were forgotten,
| students see 403 on content they own — visible and harmless — rather than
| unenrolled strangers reading paid content.
*/
it('fails safe: the student branch denies until phase 6 completes it', function (): void {
    $course = Course::factory()->published()->create();

    expect(app(EnrollmentAccessService::class)->grantsAccess(
        User::factory()->student()->create(),
        $course,
    ))->toBeFalse();
});

/*
| INSTRUCTORS DO NOT AUTHOR CONTENT IN V1 (FR-INS-08).
*/
it('stops an assigned instructor editing course content', function (): void {
    $course = Course::factory()->create();
    $instructor = User::factory()->instructor()->create();
    $course->instructors()->attach($instructor);

    $policy = app(App\Policies\CoursePolicy::class);

    expect($policy->update($instructor, $course))->toBeFalse()
        ->and($policy->delete($instructor, $course))->toBeFalse()
        ->and($policy->publish($instructor, $course))->toBeFalse()
        ->and($policy->manageContent($instructor, $course))->toBeFalse()
        ->and($policy->manageInstructors($instructor, $course))->toBeFalse()
        // But they DO author assessments and see their students.
        ->and($policy->manageAssessments($instructor, $course))->toBeTrue()
        ->and($policy->viewStudents($instructor, $course))->toBeTrue();
});

it('never permits permanent deletion of a course', function (): void {
    // FR-CRS-06: students paid for it; progress and orders reference it.
    expect(app(App\Policies\CoursePolicy::class)->forceDelete(
        User::factory()->superAdmin()->create(),
        Course::factory()->create(),
    ))->toBeFalse();
});

/*
| UNPUBLISHED CONTENT IS INVISIBLE TO STUDENTS (FR-CNT-05).
*/
it('hides an unpublished lesson from students even inside a published course', function (): void {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->forCourse($course)->published()->create();
    $lesson = Lesson::factory()->forModule($module)->create(); // unpublished

    $policy = app(App\Policies\LessonPolicy::class);

    expect($policy->view(User::factory()->student()->create(), $lesson))->toBeFalse()
        ->and($policy->view(null, $lesson))->toBeFalse()
        ->and($policy->view(User::factory()->superAdmin()->create(), $lesson))->toBeTrue();
});

it('hides a published lesson inside an unpublished module', function (): void {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->forCourse($course)->create(); // unpublished
    $lesson = Lesson::factory()->forModule($module)->published()->create();

    expect(app(App\Policies\LessonPolicy::class)->view(User::factory()->student()->create(), $lesson))
        ->toBeFalse();
});

/*
| MEDIA — the last gate before the bytes (AC-01, AC-02, AC-20).
*/
it('denies media to guests', function (): void {
    $file = MediaFile::factory()->create();

    expect(app(App\Policies\MediaFilePolicy::class)->access(null, $file))->toBeFalse();
});

it('denies media to a student with no enrollment', function (): void {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->forCourse($course)->published()->create();
    $lesson = Lesson::factory()->forModule($module)->published()->create();
    $file = MediaFile::factory()->attachedTo($lesson)->create();

    expect(app(App\Policies\MediaFilePolicy::class)->access(
        User::factory()->student()->create(),
        $file,
    ))->toBeFalse();
});

it('allows a course thumbnail to be public', function (): void {
    // The ONE exception — thumbnails appear on the public catalogue by design.
    $course = Course::factory()->published()->create();
    $file = MediaFile::factory()->thumbnail()->attachedTo($course)->create();

    expect(app(App\Policies\MediaFilePolicy::class)->access(null, $file))->toBeTrue();
});

it('denies media whose owning course cannot be resolved', function (): void {
    // An unresolvable owner must be treated as DENY, never as "no
    // restriction" — that inversion is the likeliest access-control bug in a
    // system like this.
    $orphan = MediaFile::factory()->create([
        'attachable_type' => User::class,
        'attachable_id' => User::factory()->create()->id,
    ]);

    expect(app(App\Policies\MediaFilePolicy::class)->access(
        User::factory()->superAdmin()->create(),
        $orphan,
    ))->toBeFalse();
});

it('refuses to download a video even when access is granted', function (): void {
    // Videos stream; they are not offered as downloads (FR-FILE-09). Enforced
    // server-side, not by omitting a button (Rule 20).
    $course = Course::factory()->create();
    $module = Module::factory()->forCourse($course)->create();
    $lesson = Lesson::factory()->forModule($module)->create();
    $video = MediaFile::factory()->video()->attachedTo($lesson)->create();

    $admin = User::factory()->superAdmin()->create();
    $policy = app(App\Policies\MediaFilePolicy::class);

    expect($policy->access($admin, $video))->toBeTrue()
        ->and($policy->download($admin, $video))->toBeFalse();
});

/*
| THE INSTRUCTOR SCOPE — the single entry point (§8.4).
*/
it('scopes courses to those an instructor is assigned to', function (): void {
    $instructor = User::factory()->instructor()->create();
    $assigned = Course::factory()->count(2)->create();
    Course::factory()->count(3)->create(); // not assigned

    foreach ($assigned as $course) {
        $course->instructors()->attach($instructor);
    }

    $scoped = Course::query()->assignedTo($instructor)->pluck('id');

    expect($scoped)->toHaveCount(2)
        ->and($scoped->all())->toEqualCanonicalizing($assigned->pluck('id')->all());
});

it('returns nothing for an instructor with no assignments', function (): void {
    Course::factory()->count(3)->create();

    expect(Course::query()->assignedTo(User::factory()->instructor()->create())->count())->toBe(0);
});
