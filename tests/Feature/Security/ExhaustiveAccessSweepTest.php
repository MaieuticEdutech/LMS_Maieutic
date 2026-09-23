<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Exhaustive access sweep — Phase 14, AC-02, AC-03, AC-26
|--------------------------------------------------------------------------
|
| Closes the specific gaps two parallel Phase 14 audits found in otherwise
| solid, already-tested access control: routes with real, correct
| deny-by-default logic that simply had zero test coverage exercising it —
| plus one that had partial coverage (only the "unpublished" case, never
| "published but wrong course").
|
| Every underlying authorization path here was independently read and
| confirmed correct BEFORE writing these tests (AttemptPolicy::start(),
| StartAttempt::handle(), AttemptPolicy::review(), AttemptHistory::mount())
| — this file exists to prove that, not to find out whether it's true by
| accident.
|
*/

use App\Enums\AttemptStatus;
use App\Enums\EnrollmentSource;
use App\Livewire\Admin\Assessments\AssessmentBuilder;
use App\Livewire\Instructor\Dashboard;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

/*
| ═══════════ AC-02: student.assessments.attempt — published, wrong course ═══════════
|
| Existing coverage (AuthorAndSitTest) only proved an UNPUBLISHED
| assessment can't be guessed by id. This proves the more realistic
| attack: a real, live, published assessment belonging to a course the
| requesting student never enrolled in.
*/
it('refuses a student not enrolled in the course to start a published assessment', function (): void {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->forCourse($course)->create();
    $lesson = Lesson::factory()->forModule($module)->create();
    $assessment = Assessment::factory()->create([
        'assessable_type' => Lesson::class,
        'assessable_id' => $lesson->id,
    ]);

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('student.assessments.attempt', $assessment))
        ->assertForbidden();

    expect(AssessmentAttempt::query()->count())->toBe(0);
});

/*
| ═══════════ AC-02: student.assessments.history — never directly tested ═══════════
*/
it('refuses a student not enrolled in the course to view assessment attempt history', function (): void {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->forCourse($course)->create();
    $lesson = Lesson::factory()->forModule($module)->create();
    $assessment = Assessment::factory()->create([
        'assessable_type' => Lesson::class,
        'assessable_id' => $lesson->id,
    ]);

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('student.assessments.history', $assessment))
        ->assertForbidden();
});

it('redirects a guest away from assessment attempt history', function (): void {
    $assessment = Assessment::factory()->create();

    expect($this->get(route('student.assessments.history', $assessment))->isRedirect())->toBeTrue();
});

/*
| ═══════════ AC-04/AC-02: student.assessments.result — zero prior route coverage ═══════════
*/
it('lets the owning student see their own attempt result', function (): void {
    $owner = User::factory()->create();
    $attempt = AssessmentAttempt::factory()->graded()->create(['user_id' => $owner->getKey()]);

    $this->actingAs($owner)
        ->get(route('student.assessments.result', $attempt))
        ->assertOk();
});

it('refuses a different student their attempt result', function (): void {
    $attempt = AssessmentAttempt::factory()->graded()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('student.assessments.result', $attempt))
        ->assertForbidden();
});

it('redirects a guest away from an attempt result', function (): void {
    $attempt = AssessmentAttempt::factory()->graded()->create();

    expect($this->get(route('student.assessments.result', $attempt))->isRedirect())->toBeTrue();
});

/*
| ═══════════ AC-03: the four instructor report screens — data isolation ═══════════
|
| ReportScreenTest already proves role-gating (student/guest refused,
| instructor let in). None of it proves instructor A's report is actually
| SCOPED to instructor A's own course rather than the whole catalogue —
| the same class of bug an IDOR sweep exists to catch, just expressed as
| data leakage in a report instead of a direct per-ID route.
*/
$reports = ['enrollments', 'course-progress', 'assessments', 'students'];

it('never shows another instructor\'s course or students in a report screen', function (string $report): void {
    // The `assessments` and `students` screens only render a course/name
    // once there's a GRADED attempt to report on — an enrollment alone
    // renders "No graded attempts in this period", which trivially
    // "passes" an assertDontSee for the wrong reason. Every fixture here
    // gets a real graded attempt so all four screens have something to
    // leak if they were going to.
    $buildInstructorWithGradedAttempt = function (string $courseTitle, string $studentName): array {
        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->published()->create(['title' => $courseTitle]);
        $course->instructors()->attach($instructor);

        $module = Module::factory()->forCourse($course)->create();
        $lesson = Lesson::factory()->forModule($module)->create();
        $assessment = Assessment::factory()->create([
            'assessable_type' => Lesson::class,
            'assessable_id' => $lesson->id,
        ]);

        $student = User::factory()->create(['name' => $studentName]);
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->getKey(),
            'course_id' => $course->getKey(),
            'source' => EnrollmentSource::Purchase,
        ]);

        AssessmentAttempt::factory()->graded()->create([
            'assessment_id' => $assessment->getKey(),
            'user_id' => $student->getKey(),
            'enrollment_id' => $enrollment->getKey(),
        ]);

        return [$instructor, $course];
    };

    [$instructorA] = $buildInstructorWithGradedAttempt('Instructor A Exclusive Course', 'Student Of A');
    $buildInstructorWithGradedAttempt('Instructor B Exclusive Course', 'Student Of B');

    $response = $this->actingAs($instructorA)->get(route("instructor.reports.{$report}"));
    $response->assertOk();

    $content = $response->getContent();

    // The four screens don't agree on which identifying string they render
    // — `enrollments`/`course-progress` show the course title but never a
    // student name, `students` shows the name but never a course title, and
    // `assessments` may show either depending on layout. Asserting on
    // "at least one of instructor A's own identifiers appears, and NEITHER
    // of instructor B's ever does" is the check that holds across all four,
    // without hard-coding each screen's particular column layout.
    expect($content)
        ->toContain('LMS') // sanity: not an empty/error page
        ->and(str_contains((string) $content, 'Instructor A Exclusive Course') || str_contains((string) $content, 'Student Of A'))
        ->toBeTrue()
        ->and($content)->not->toContain('Instructor B Exclusive Course')
        ->and($content)->not->toContain('Student Of B');
})->with($reports);

/*
| ═══════════ AC-03: instructor dashboard — aggregate-count isolation ═══════════
*/
it('counts only the signed-in instructor\'s own courses and students on the dashboard', function (): void {
    $instructorA = User::factory()->instructor()->create();
    $courseA = Course::factory()->published()->create();
    $courseA->instructors()->attach($instructorA);
    Enrollment::factory()->create([
        'user_id' => User::factory()->create()->getKey(),
        'course_id' => $courseA->getKey(),
        'source' => EnrollmentSource::Purchase,
    ]);

    $instructorB = User::factory()->instructor()->create();
    $courseB = Course::factory()->published()->create();
    $courseB->instructors()->attach($instructorB);

    // Instructor B has two students; if the dashboard leaked across
    // instructors, A's counts would be inflated by these.
    Enrollment::factory()->count(2)->create([
        'course_id' => $courseB->getKey(),
        'source' => EnrollmentSource::Purchase,
    ]);

    $this->actingAs($instructorA);

    Livewire::test(Dashboard::class)
        ->assertViewHas('assignedCourseCount', 1)
        ->assertViewHas('studentCount', 1);
});

/*
| ═══════════ AC-26: the database constraint itself, not just the app check ═══════════
|
| StartAttempt's application-level pre-check (tested in
| AttemptLifecycleTest) is a friendly-message optimisation, not the real
| guarantee — its own docblock says so. This proves the partial unique
| index (assessment_attempts_one_in_progress) rejects a second in-progress
| row even when application code is bypassed entirely, which is what
| actually closes the concurrent-double-start race.
*/
it('rejects a second in-progress attempt at the database level, bypassing application logic', function (): void {
    $assessment = Assessment::factory()->create();
    $student = User::factory()->student()->create();
    $enrollment = Enrollment::factory()->create(['user_id' => $student->id]);

    AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->id,
        'user_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'status' => AttemptStatus::InProgress,
    ]);

    expect(fn () => AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->id,
        'user_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'status' => AttemptStatus::InProgress,
        'attempt_number' => 2,
    ]))->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

/*
| ═══════════ AC-34: assessment.unpublished had a logging call, never a test ═══════════
*/
it('records an audit entry when an assessment is unpublished', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $assessment = Assessment::factory()->create();

    $this->actingAs($admin);

    Livewire::test(AssessmentBuilder::class, ['assessment' => $assessment])
        ->call('unpublish');

    expect(AuditLog::query()->where('action', 'assessment.unpublished')->exists())->toBeTrue();
});

/*
| ═══════════ AC-04 boundary: checkout.status — documented, was untested ═══════════
|
| Not a route-param IDOR in the classic sense (order_number is a 10-char
| random token per OrderFactory, not a sequential id) — the component's own
| docblock names this the same "possession of an unguessable token is the
| authorisation" trade the certificate-verification route uses, and it's
| deliberate: a guest who just paid has no session yet, so this page IS
| how they find out where their activation email went (AC-07). Genuinely
| untested before this pass, so it locks in what's intentional rather than
| assuming it.
*/
it('shows a guest holding the order number their own order status, buyer email included by design', function (): void {
    $order = Order::factory()->paid()->create();

    $this->get(route('checkout.status', $order))
        ->assertOk()
        ->assertSee($order->buyer_email);
});

it('never lets the order number be guessed from a sequential id', function (): void {
    $orderA = Order::factory()->create();
    $orderB = Order::factory()->create();

    expect($orderA->order_number)->not->toBe($orderB->order_number)
        ->and(strlen($orderA->order_number))->toBeGreaterThanOrEqual(10);
});
