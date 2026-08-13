<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Actions\Enrollment\RevokeEnrollment;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Services\Enrollment\EnrollmentAccessService;

/*
|--------------------------------------------------------------------------
| Phase 7 · Course player (AC-02, AC-18, FR-STU-08, FR-STU-09)
|--------------------------------------------------------------------------
|
| The player is the only screen that exposes lesson content, so the access
| tests here matter as much as the ones guarding the media routes themselves.
| A policy that denies while a screen renders the content anyway is precisely
| the failure a controller-level test would miss.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();

    $this->course = Course::factory()->published()->create();

    $module = Module::factory()->forCourse($this->course)->published()->create();
    $this->lessonOne = Lesson::factory()->forModule($module)->published()->atPosition(0)
        ->create(['type' => LessonType::Text, 'title' => 'Opening lesson', 'body' => '<p>Hello</p>']);
    $this->lessonTwo = Lesson::factory()->forModule($module)->published()->atPosition(1)
        ->create(['type' => LessonType::Text, 'title' => 'Second lesson', 'body' => '<p>More</p>']);

    $this->enrol = fn (User $u) => app(GrantEnrollment::class)
        ->handle($u, $this->course, EnrollmentSource::AdminGrant, $this->admin);
});

/*
| ═══════════════ AC-02 — NO ENROLLMENT, NO CONTENT ═══════════════
*/
it('redirects a guest away from the player', function (): void {
    $this->get(route('student.courses.play', $this->course))->assertRedirect('/login');
});

it('refuses an authenticated student with no enrollment', function (): void {
    $this->actingAs($this->student)
        ->get(route('student.courses.play', $this->course))
        ->assertForbidden();
});

it('refuses a student enrolled in a different course', function (): void {
    app(GrantEnrollment::class)->handle($this->student, Course::factory()->create(), EnrollmentSource::Purchase);

    $this->actingAs($this->student)
        ->get(route('student.courses.play', $this->course))
        ->assertForbidden();
});

it('refuses every status that does not grant access', function (EnrollmentStatus $status): void {
    $enrollment = ($this->enrol)($this->student);
    $enrollment->forceFill(['status' => $status])->save();
    app(EnrollmentAccessService::class)->flush();

    $this->actingAs($this->student)
        ->get(route('student.courses.play', $this->course))
        ->assertForbidden();
})->with([
    'suspended' => EnrollmentStatus::Suspended,
    'expired' => EnrollmentStatus::Expired,
    'refunded' => EnrollmentStatus::Refunded,
]);

it('stops serving the player the moment access is revoked', function (): void {
    $enrollment = ($this->enrol)($this->student);

    $this->actingAs($this->student)
        ->get(route('student.courses.play', $this->course))
        ->assertOk();

    app(RevokeEnrollment::class)->handle($enrollment, $this->admin, 'Chargeback.');

    $this->actingAs($this->student)
        ->get(route('student.courses.play', $this->course))
        ->assertForbidden();
});

/*
| ═══════════════ ENROLLED STUDENTS DO GET IN ═══════════════
*/
it('renders the player for an enrolled student', function (): void {
    ($this->enrol)($this->student);

    $this->actingAs($this->student)
        ->get(route('student.courses.play', $this->course))
        ->assertOk()
        ->assertSee($this->course->title)
        ->assertSee('Opening lesson');
});

it('resumes at the last lesson rather than the first', function (): void {
    // AC-18's sibling: the course-level resume point.
    $enrollment = ($this->enrol)($this->student);
    $enrollment->forceFill(['last_lesson_id' => $this->lessonTwo->id])->save();

    $this->actingAs($this->student)
        ->get(route('student.courses.play', $this->course))
        ->assertOk()
        ->assertSee('Second lesson');
});

it('falls back to the first lesson when the resume point is no longer published', function (): void {
    // A 404 on "continue learning" is worse than starting over.
    $enrollment = ($this->enrol)($this->student);
    $enrollment->forceFill(['last_lesson_id' => $this->lessonTwo->id])->save();
    $this->lessonTwo->forceFill(['is_published' => false])->save();

    $this->actingAs($this->student)
        ->get(route('student.courses.play', $this->course))
        ->assertOk()
        ->assertSee('Opening lesson');
});

/*
| ═══════════════ UNPUBLISHED CONTENT IS INVISIBLE ═══════════════
*/
it('404s on a lesson that is not published', function (): void {
    ($this->enrol)($this->student);
    $this->lessonTwo->forceFill(['is_published' => false])->save();

    $this->actingAs($this->student)
        ->get(route('student.courses.play', [$this->course, $this->lessonTwo]))
        ->assertNotFound();
});

it('404s on a lesson belonging to another course', function (): void {
    ($this->enrol)($this->student);

    $other = Course::factory()->published()->create();
    $otherModule = Module::factory()->forCourse($other)->published()->create();
    $foreign = Lesson::factory()->forModule($otherModule)->published()->create();

    $this->actingAs($this->student)
        ->get(route('student.courses.play', [$this->course, $foreign]))
        ->assertNotFound();
});

it('hides lessons inside an unpublished module even when the lessons are published', function (): void {
    ($this->enrol)($this->student);

    $draftModule = Module::factory()->forCourse($this->course)->create(['is_published' => false]);
    $hidden = Lesson::factory()->forModule($draftModule)->published()
        ->create(['title' => 'Hidden lesson', 'type' => LessonType::Text]);

    $response = $this->actingAs($this->student)->get(route('student.courses.play', $this->course));

    $response->assertOk()->assertDontSee('Hidden lesson');

    $this->actingAs($this->student)
        ->get(route('student.courses.play', [$this->course, $hidden]))
        ->assertNotFound();
});

/*
| ═══════════════ PROGRESS ═══════════════
*/
it('records the student as having visited on arrival', function (): void {
    $enrollment = ($this->enrol)($this->student);

    $this->actingAs($this->student)->get(route('student.courses.play', $this->course))->assertOk();

    expect(LessonProgress::query()
        ->where('enrollment_id', $enrollment->id)
        ->where('lesson_id', $this->lessonOne->id)
        ->exists())->toBeTrue();

    // The pointer that makes "continue learning" work.
    expect($enrollment->refresh()->last_lesson_id)->toBe($this->lessonOne->id);
});

/*
| ═══════════════ NFR-PERF-03 — BOUNDED QUERIES ═══════════════
*/
it('does not issue a query per lesson', function (): void {
    ($this->enrol)($this->student);

    // Twenty more lessons. If the curriculum sidebar asked "is this complete?"
    // per row, this count would climb with the course size — which is the
    // entire failure CoursePlayerService exists to prevent.
    $module = Module::factory()->forCourse($this->course)->published()->create();
    Lesson::factory()->forModule($module)->published()->count(20)
        ->create(['type' => LessonType::Text]);

    $queries = 0;
    DB::listen(static function () use (&$queries): void {
        $queries++;
    });

    $this->actingAs($this->student)->get(route('student.courses.play', $this->course))->assertOk();

    // Asserted as a COUNT, not a duration: a duration passes on a fast laptop
    // and fails in production. The ceiling is generous — the point is that it
    // does not scale with lesson count.
    expect($queries)->toBeLessThan(40);
});
