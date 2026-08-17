<?php

declare(strict_types=1);

use App\Models\Assessment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| QA PROBE — the full §2 access-control matrix, every cell
|--------------------------------------------------------------------------
|
| Plan IDs: RBAC-G01 … G15, RBAC-S01 … S16, RBAC-I01 … I12, RBAC-A01 … A04.
|
| Route-level only. Object-level (IDOR) cells live in the authoring probe
| and in InstructorProgressTest, which already covers X01/X04/X05.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->instructor = User::factory()->instructor()->create();
    $this->student = User::factory()->create();

    $this->course = Course::factory()->published()->create();
    $module = Module::factory()->forCourse($this->course)->published()->create();
    $this->lesson = Lesson::factory()->forModule($module)->published()->atPosition(0)->create();

    $this->assessment = Assessment::factory()->create([
        'assessable_type' => Lesson::class,
        'assessable_id' => $this->lesson->getKey(),
    ]);
});

/**
 * Every admin URL, built lazily so route params resolve per test.
 *
 * @return array<string, string>
 */
function adminUrls(): array
{
    $course = Course::query()->firstOrFail();
    $assessment = Assessment::query()->firstOrFail();
    $user = User::factory()->create();

    return [
        'dashboard' => route('admin.dashboard'),
        'students.index' => route('admin.students.index'),
        'students.create' => route('admin.students.create'),
        'students.edit' => route('admin.students.edit', $user),
        'students.show' => route('admin.students.show', $user),
        'instructors.index' => route('admin.instructors.index'),
        'instructors.create' => route('admin.instructors.create'),
        'courses.index' => route('admin.courses.index'),
        'courses.create' => route('admin.courses.create'),
        'courses.builder' => route('admin.courses.builder', $course),
        'enrollments.index' => route('admin.enrollments.index'),
        'enrollments.create' => route('admin.enrollments.create'),
        'assessments.index' => route('admin.assessments.index'),
        'assessments.builder' => route('admin.assessments.builder', $assessment),
        'assessments.results' => route('admin.assessments.results', $assessment),
        'settings.index' => route('admin.settings.index'),
        'audit-log.index' => route('admin.audit-log.index'),
    ];
}

/** @return array<string, string> */
function instructorUrls(): array
{
    $course = Course::query()->firstOrFail();
    $assessment = Assessment::query()->firstOrFail();

    return [
        'home' => route('instructor.home'),
        'courses.index' => route('instructor.courses.index'),
        'courses.show' => route('instructor.courses.show', $course),
        'assessments.index' => route('instructor.assessments.index'),
        'assessments.builder' => route('instructor.assessments.builder', $assessment),
        'assessments.results' => route('instructor.assessments.results', $assessment),
    ];
}

/** @return array<string, string> */
function studentUrls(): array
{
    $course = Course::query()->firstOrFail();
    $assessment = Assessment::query()->firstOrFail();

    return [
        'home' => route('student.home'),
        'courses.index' => route('student.courses.index'),
        'courses.play' => route('student.courses.play', $course),
        'assessments.attempt' => route('student.assessments.attempt', $assessment),
        'assessments.history' => route('student.assessments.history', $assessment),
    ];
}

/*
| ═══════════ RBAC-G01 … G13 — a guest reaches nothing protected ═══════════
*/

it('redirects a guest away from every admin url', function (): void {
    foreach (adminUrls() as $name => $url) {
        expect($this->get($url)->isRedirect())->toBeTrue("admin.{$name} did not redirect a guest");
    }
});

it('redirects a guest away from every instructor url', function (): void {
    foreach (instructorUrls() as $name => $url) {
        expect($this->get($url)->isRedirect())->toBeTrue("instructor.{$name} did not redirect a guest");
    }
});

it('redirects a guest away from every student url', function (): void {
    foreach (studentUrls() as $name => $url) {
        expect($this->get($url)->isRedirect())->toBeTrue("student.{$name} did not redirect a guest");
    }
});

/*
| ═══════════ RBAC-G14 / G15 — the catalogue IS public, metadata only ═══════
*/

it('serves the public catalogue to a guest', function (): void {
    $this->get(route('catalogue.index'))->assertOk();
    $this->get(route('catalogue.show', $this->course))->assertOk();
});

it('never exposes lesson bodies on the public course page', function (): void {
    $this->lesson->forceFill(['body' => 'SECRET_LESSON_BODY_MARKER'])->save();

    $this->get(route('catalogue.show', $this->course))
        ->assertOk()
        ->assertDontSee('SECRET_LESSON_BODY_MARKER');
});

/*
| ═══════════ RBAC-S01 … S16 — a student reaches no admin or instructor url ══
*/

it('forbids a student every admin url', function (): void {
    foreach (adminUrls() as $name => $url) {
        expect($this->actingAs($this->student)->get($url)->status())
            ->toBe(403, "admin.{$name} did not return 403 for a student");
    }
});

it('forbids a student every instructor url', function (): void {
    foreach (instructorUrls() as $name => $url) {
        expect($this->actingAs($this->student)->get($url)->status())
            ->toBe(403, "instructor.{$name} did not return 403 for a student");
    }
});

/*
| ═══════════ RBAC-I01 … I11 — an instructor reaches no admin or student url ═
*/

it('forbids an instructor every admin url', function (): void {
    foreach (adminUrls() as $name => $url) {
        expect($this->actingAs($this->instructor)->get($url)->status())
            ->toBe(403, "admin.{$name} did not return 403 for an instructor");
    }
});

it('forbids an instructor every student url', function (): void {
    foreach (studentUrls() as $name => $url) {
        expect($this->actingAs($this->instructor)->get($url)->status())
            ->toBe(403, "student.{$name} did not return 403 for an instructor");
    }
});

/*
| ═══════════ RBAC-A01 / A02 — role groups are exclusive both ways ═══════════
*/

it('forbids a super admin the instructor area', function (): void {
    foreach (instructorUrls() as $name => $url) {
        expect($this->actingAs($this->admin)->get($url)->status())
            ->toBe(403, "instructor.{$name} did not return 403 for a super admin");
    }
});

it('forbids a super admin the student area', function (): void {
    foreach (studentUrls() as $name => $url) {
        expect($this->actingAs($this->admin)->get($url)->status())
            ->toBe(403, "student.{$name} did not return 403 for a super admin");
    }
});

/*
| ═══════════ RBAC-A03 — the admin CAN reach every admin url ═══════════
*/

it('serves every admin url to a super admin', function (): void {
    foreach (adminUrls() as $name => $url) {
        expect($this->actingAs($this->admin)->get($url)->status())
            ->toBe(200, "admin.{$name} did not return 200 for a super admin");
    }
});

/*
| ═══════════ RBAC-I12 — the instructor nav carries exactly three entries ════
*/

it('shows an instructor only their own three nav items', function (): void {
    $html = $this->actingAs($this->instructor)->get(route('instructor.home'))->content();

    expect($html)->toContain(route('instructor.courses.index'))
        ->and($html)->toContain(route('instructor.assessments.index'))
        ->and($html)->not->toContain('/admin/students')
        ->and($html)->not->toContain('/admin/enrollments')
        ->and($html)->not->toContain('/admin/settings')
        ->and($html)->not->toContain('/admin/audit-log');
});

/*
| ═══════════ RBAC-G12 / G13 — protected media ═══════════
*/

it('refuses an unsigned media stream url', function (): void {
    $media = App\Models\MediaFile::factory()->create();

    $this->get(route('media.stream', $media))->assertForbidden();
});

/*
| ═══════════ Enrollment status gate — X08, X09, X10 ═══════════
*/

it('grants or denies the player by enrollment status', function (string $status, bool $allowed): void {
    $enrollment = Enrollment::factory()->create([
        'user_id' => $this->student->getKey(),
        'course_id' => $this->course->getKey(),
    ]);
    $enrollment->forceFill(['status' => $status])->save();
    app(App\Services\Enrollment\EnrollmentAccessService::class)->flush();

    $response = $this->actingAs($this->student)->get(route('student.courses.play', $this->course));

    expect($response->status())->toBe($allowed ? 200 : 403);
})->with([
    ['active', true],
    ['completed', true],
    ['suspended', false],
    ['expired', false],
    ['refunded', false],
]);
