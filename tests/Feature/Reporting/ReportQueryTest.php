<?php

declare(strict_types=1);

use App\Enums\AttemptStatus;
use App\Enums\EnrollmentSource;
use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AttemptAnswer;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Question;
use App\Models\User;
use App\Services\Reporting\AssessmentReport;
use App\Services\Reporting\CourseProgressReport;
use App\Services\Reporting\DateRange;
use App\Services\Reporting\EnrollmentReport;
use App\Services\Reporting\StudentReport;

/*
|--------------------------------------------------------------------------
| Phase 13 · report figures, date boundaries and role scope
|--------------------------------------------------------------------------
|
| phases.md requires three things of every report, and these are them:
|   - correct figures against a known fixture
|   - date-range filtering inclusive and correct AT THE BOUNDARIES
|   - instructors see only assigned-course data and no financial figures
|
| The fixture is fixed and small enough to reason about by hand: two courses,
| one assigned to the instructor and one not, so every scope test has a
| control that must never appear.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->instructor = User::factory()->instructor()->create();

    $this->mine = Course::factory()->published()->create(['title' => 'Assigned Course']);
    $this->theirs = Course::factory()->published()->create(['title' => 'Unassigned Course']);
    $this->mine->instructors()->attach($this->instructor);

    $this->enrol = function (Course $course, EnrollmentSource $source, string $at, int $progress = 0, bool $completed = false): Enrollment {
        return Enrollment::factory()->create([
            'user_id' => User::factory()->create()->getKey(),
            'course_id' => $course->getKey(),
            'source' => $source,
            'enrolled_at' => $at,
            'progress_percentage' => $progress,
            'completed_at' => $completed ? $at : null,
            'last_accessed_at' => $progress > 0 ? $at : null,
        ]);
    };
});

/**
 * A report row that must exist. Keeps the offset accesses below honest —
 * firstWhere() is nullable, and a test that silently indexes null reports a
 * confusing failure instead of "the row was not there".
 *
 * @param  array<string, mixed>|null  $row
 * @return array<string, mixed>
 */
function reportRow(?array $row): array
{
    expect($row)->not->toBeNull();

    return $row ?? [];
}

/*
| ═══════════ FR-RPT-01 — enrollment report ═══════════
*/

it('splits enrollments by source', function (): void {
    ($this->enrol)($this->mine, EnrollmentSource::Purchase, '2026-03-10');
    ($this->enrol)($this->mine, EnrollmentSource::Purchase, '2026-03-11');
    ($this->enrol)($this->mine, EnrollmentSource::AdminGrant, '2026-03-12');

    $row = app(EnrollmentReport::class)->perCourse($this->admin, DateRange::unbounded())->firstOrFail();

    expect($row['purchase'])->toBe(2)
        ->and($row['admin_grant'])->toBe(1)
        ->and($row['import'])->toBe(0)
        ->and($row['total'])->toBe(3);
});

it('counts a purchase and a grant separately in the totals strip', function (): void {
    ($this->enrol)($this->mine, EnrollmentSource::Purchase, '2026-03-10');
    ($this->enrol)($this->theirs, EnrollmentSource::AdminGrant, '2026-03-10');

    $totals = app(EnrollmentReport::class)->totals($this->admin, DateRange::unbounded());

    expect($totals['purchase'])->toBe(1)
        ->and($totals['admin_grant'])->toBe(1)
        ->and($totals['total'])->toBe(2);
});

it('groups enrollments by month', function (): void {
    ($this->enrol)($this->mine, EnrollmentSource::Purchase, '2026-03-02');
    ($this->enrol)($this->mine, EnrollmentSource::Purchase, '2026-03-28');
    ($this->enrol)($this->mine, EnrollmentSource::Purchase, '2026-04-02');

    $periods = app(EnrollmentReport::class)->perPeriod($this->admin, DateRange::unbounded());

    expect($periods->pluck('total', 'period')->all())->toBe(['2026-03' => 2, '2026-04' => 1]);
});

/*
| ═══════════ FR-RPT-06 — THE BOUNDARIES ═══════════
|
| The whole reason DateRange widens `to` to end-of-day. Written naively these
| two tests fail by exactly one day, and the report is merely a little low
| rather than obviously broken — which is why it survives review.
*/

it('includes an enrollment made on the last day of the range', function (): void {
    ($this->enrol)($this->mine, EnrollmentSource::Purchase, '2026-03-31 16:45:00');

    $range = DateRange::fromStrings('2026-03-01', '2026-03-31');

    expect(app(EnrollmentReport::class)->totals($this->admin, $range)['total'])->toBe(1);
});

it('includes an enrollment made at the first instant of the range', function (): void {
    ($this->enrol)($this->mine, EnrollmentSource::Purchase, '2026-03-01 00:00:00');

    $range = DateRange::fromStrings('2026-03-01', '2026-03-31');

    expect(app(EnrollmentReport::class)->totals($this->admin, $range)['total'])->toBe(1);
});

it('excludes enrollments just outside either end', function (): void {
    ($this->enrol)($this->mine, EnrollmentSource::Purchase, '2026-02-28 23:59:59');
    ($this->enrol)($this->mine, EnrollmentSource::Purchase, '2026-04-01 00:00:01');

    $range = DateRange::fromStrings('2026-03-01', '2026-03-31');

    expect(app(EnrollmentReport::class)->totals($this->admin, $range)['total'])->toBe(0);
});

it('treats an unparseable or blank date as unbounded rather than erroring', function (): void {
    ($this->enrol)($this->mine, EnrollmentSource::Purchase, '2026-03-10');

    expect(DateRange::fromStrings('not a date', '')->isUnbounded())->toBeTrue()
        ->and(app(EnrollmentReport::class)->totals($this->admin, DateRange::fromStrings('nonsense', null))['total'])->toBe(1);
});

it('labels the range for the header and the export filename', function (): void {
    expect(DateRange::fromStrings('2026-03-01', '2026-03-31')->label())->toBe('1 Mar 2026 – 31 Mar 2026')
        ->and(DateRange::unbounded()->label())->toBe('All time');
});

/*
| ═══════════ FR-RPT-03 — the progress funnel ═══════════
*/

it('reports the progress funnel per course', function (): void {
    ($this->enrol)($this->mine, EnrollmentSource::Purchase, '2026-03-01', progress: 0);
    ($this->enrol)($this->mine, EnrollmentSource::Purchase, '2026-03-01', progress: 40);
    ($this->enrol)($this->mine, EnrollmentSource::Purchase, '2026-03-01', progress: 100, completed: true);

    $row = reportRow(app(CourseProgressReport::class)->perCourse($this->admin, DateRange::unbounded())
        ->firstWhere('course', 'Assigned Course'));

    expect($row['enrolled'])->toBe(3)
        ->and($row['started'])->toBe(2)      // the 0% one has not started
        ->and($row['in_progress'])->toBe(1)  // started but not completed
        ->and($row['completed'])->toBe(1)
        ->and($row['average'])->toBe(47);    // (0 + 40 + 100) / 3
});

/*
| ═══════════ FR-RPT-05 — the student report ═══════════
*/

it('reports a student who has never opened a lesson as such', function (): void {
    ($this->enrol)($this->mine, EnrollmentSource::Purchase, '2026-03-01', progress: 0);

    $row = app(StudentReport::class)->rows($this->admin, DateRange::unbounded())->firstOrFail();

    expect($row['enrollments'])->toBe(1)
        ->and($row['average_progress'])->toBe(0)
        ->and($row['last_activity'])->toBe('Never opened a lesson')
        // Null rather than 0.0: no graded attempt means no average, and 0%
        // would read as having failed everything.
        ->and($row['average_score'])->toBeNull();
});

it('does not multiply enrollment counts when a student has many attempts', function (): void {
    $student = User::factory()->create(['name' => 'Fan Out']);

    $enrollment = Enrollment::factory()->create([
        'user_id' => $student->getKey(),
        'course_id' => $this->mine->getKey(),
        'enrolled_at' => '2026-03-01',
    ]);

    $assessment = Assessment::factory()->create([
        'assessable_type' => Course::class,
        'assessable_id' => $this->mine->getKey(),
    ]);

    // Three graded attempts against ONE enrollment. Joined naively this
    // reports the student as enrolled three times.
    foreach ([50, 70, 90] as $i => $score) {
        AssessmentAttempt::factory()->graded()->create([
            'assessment_id' => $assessment->getKey(),
            'user_id' => $student->getKey(),
            'enrollment_id' => $enrollment->getKey(),
            'attempt_number' => $i + 1,
            'score_percentage' => $score,
        ]);
    }

    $row = reportRow(app(StudentReport::class)->rows($this->admin, DateRange::unbounded())
        ->firstWhere('student', 'Fan Out'));

    expect($row['enrollments'])->toBe(1)
        ->and($row['attempts'])->toBe(3)
        ->and($row['average_score'])->toBe(70.0);
});

it('searches students by name and email', function (): void {
    $wanted = User::factory()->create(['name' => 'Findable Person', 'email' => 'findable@lms.test']);
    $other = User::factory()->create(['name' => 'Other Person', 'email' => 'other@lms.test']);

    foreach ([$wanted, $other] as $student) {
        Enrollment::factory()->create([
            'user_id' => $student->getKey(),
            'course_id' => $this->mine->getKey(),
            'enrolled_at' => '2026-03-01',
        ]);
    }

    $rows = app(StudentReport::class)->rows($this->admin, DateRange::unbounded(), 'findable@');

    expect($rows)->toHaveCount(1)
        ->and(reportRow($rows->first())['student'])->toBe('Findable Person');
});

/*
| ═══════════ FR-RPT-04 — assessment and per-question ═══════════
*/

it('reports attempts, average and pass rate for an assessment', function (): void {
    $assessment = Assessment::factory()->create([
        'assessable_type' => Course::class,
        'assessable_id' => $this->mine->getKey(),
        'title' => 'Final Test',
    ]);

    foreach ([[80, true], [40, false], [60, true]] as [$score, $passed]) {
        AssessmentAttempt::factory()->graded()->create([
            'assessment_id' => $assessment->getKey(),
            'score_percentage' => $score,
            'is_passed' => $passed,
            'submitted_at' => '2026-03-10',
        ]);
    }

    $row = app(AssessmentReport::class)->perAssessment($this->admin, DateRange::unbounded())->firstOrFail();

    expect($row['assessment'])->toBe('Final Test')
        ->and($row['course'])->toBe('Assigned Course')
        ->and($row['attempts'])->toBe(3)
        ->and($row['average'])->toBe(60.0)
        ->and($row['pass_rate'])->toBe(66.7);
});

it('ignores ungraded attempts so they cannot drag the average down', function (): void {
    $assessment = Assessment::factory()->create([
        'assessable_type' => Course::class,
        'assessable_id' => $this->mine->getKey(),
    ]);

    AssessmentAttempt::factory()->graded()->create([
        'assessment_id' => $assessment->getKey(),
        'score_percentage' => 80,
        'is_passed' => true,
        'submitted_at' => '2026-03-10',
    ]);

    AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->getKey(),
        'status' => AttemptStatus::InProgress,
        'score_percentage' => null,
        'attempt_number' => 2,
    ]);

    $row = app(AssessmentReport::class)->perAssessment($this->admin, DateRange::unbounded())->firstOrFail();

    expect($row['attempts'])->toBe(1)
        ->and($row['average'])->toBe(80.0);
});

it('ranks questions by correct rate, weakest first', function (): void {
    $assessment = Assessment::factory()->create([
        'assessable_type' => Course::class,
        'assessable_id' => $this->mine->getKey(),
    ]);

    $easy = Question::factory()->create([
        'assessment_id' => $assessment->getKey(),
        'type' => QuestionType::SingleChoice,
        'body' => 'The easy one',
        'position' => 0,
    ]);
    $hard = Question::factory()->create([
        'assessment_id' => $assessment->getKey(),
        'type' => QuestionType::SingleChoice,
        'body' => 'The suspicious one',
        'position' => 1,
    ]);

    foreach ([true, true] as $i => $ignored) {
        $attempt = AssessmentAttempt::factory()->graded()->create([
            'assessment_id' => $assessment->getKey(),
            'attempt_number' => $i + 1,
            'submitted_at' => '2026-03-10',
        ]);

        AttemptAnswer::factory()->create([
            'attempt_id' => $attempt->getKey(),
            'question_id' => $easy->getKey(),
            'is_correct' => true,
        ]);
        AttemptAnswer::factory()->create([
            'attempt_id' => $attempt->getKey(),
            'question_id' => $hard->getKey(),
            'is_correct' => false,
        ]);
    }

    $rows = app(AssessmentReport::class)->perQuestion($assessment, DateRange::unbounded());

    // Weakest first: the item to go and look at is the first thing on screen.
    expect(reportRow($rows->first())['question'])->toBe('The suspicious one')
        ->and(reportRow($rows->first())['correct_rate'])->toBe(0.0)
        ->and(reportRow($rows->last())['correct_rate'])->toBe(100.0);
});

/*
| ═══════════ FR-RPT-07 — THE SCOPE RULE ═══════════
|
| Every report, one test each. A missing scope on an aggregate does not leak
| one row — it leaks the whole table, into a CSV somebody then forwards.
*/

it('shows an instructor only their assigned course in every report', function (): void {
    ($this->enrol)($this->mine, EnrollmentSource::Purchase, '2026-03-01', progress: 50);
    ($this->enrol)($this->theirs, EnrollmentSource::Purchase, '2026-03-01', progress: 50);

    $range = DateRange::unbounded();

    $enrollmentCourses = app(EnrollmentReport::class)->perCourse($this->instructor, $range)->pluck('course');
    $progressCourses = app(CourseProgressReport::class)->perCourse($this->instructor, $range)->pluck('course');

    expect($enrollmentCourses)->toContain('Assigned Course')
        ->and($enrollmentCourses)->not->toContain('Unassigned Course')
        ->and($progressCourses)->toContain('Assigned Course')
        ->and($progressCourses)->not->toContain('Unassigned Course');
});

it('shows an instructor only students enrolled in their own course', function (): void {
    $mine = User::factory()->create(['name' => 'My Student']);
    $theirs = User::factory()->create(['name' => 'Their Student']);

    Enrollment::factory()->create(['user_id' => $mine->getKey(), 'course_id' => $this->mine->getKey(), 'enrolled_at' => '2026-03-01']);
    Enrollment::factory()->create(['user_id' => $theirs->getKey(), 'course_id' => $this->theirs->getKey(), 'enrolled_at' => '2026-03-01']);

    $students = app(StudentReport::class)->rows($this->instructor, DateRange::unbounded())->pluck('student');

    expect($students)->toContain('My Student')
        ->and($students)->not->toContain('Their Student');
});

it('shows an instructor only assessments on their own course', function (): void {
    foreach ([[$this->mine, 'Mine'], [$this->theirs, 'Theirs']] as [$course, $title]) {
        $assessment = Assessment::factory()->create([
            'assessable_type' => Course::class,
            'assessable_id' => $course->getKey(),
            'title' => $title,
        ]);

        AssessmentAttempt::factory()->graded()->create([
            'assessment_id' => $assessment->getKey(),
            'submitted_at' => '2026-03-10',
        ]);
    }

    $titles = app(AssessmentReport::class)->perAssessment($this->instructor, DateRange::unbounded())->pluck('assessment');

    expect($titles)->toContain('Mine')->and($titles)->not->toContain('Theirs');
});

it('reports nothing at all for a student who reaches a report service', function (): void {
    ($this->enrol)($this->mine, EnrollmentSource::Purchase, '2026-03-01');

    // Deny-safe. Reaching here is a routing mistake, and an empty scope fails
    // closed while that mistake is found.
    $student = User::factory()->create();

    expect(app(EnrollmentReport::class)->perCourse($student, DateRange::unbounded()))->toBeEmpty()
        ->and(app(StudentReport::class)->rows($student, DateRange::unbounded()))->toBeEmpty();
});

it('never lets an instructor see financial permission', function (): void {
    $scope = app(App\Services\Reporting\ReportScope::class);

    expect($scope->maySeeFinancials($this->instructor))->toBeFalse()
        ->and($scope->maySeeFinancials($this->admin))->toBeTrue();
});
