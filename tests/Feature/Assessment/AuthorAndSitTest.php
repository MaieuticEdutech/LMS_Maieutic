<?php

declare(strict_types=1);

use App\Actions\Assessment\CreateAssessment;
use App\Actions\Assessment\CreateQuestion;
use App\Actions\Assessment\PublishAssessment;
use App\Actions\Assessment\SaveAnswer;
use App\Actions\Assessment\StartAttempt;
use App\Actions\Assessment\SubmitAttempt;
use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\AssessmentType;
use App\Enums\AttemptStatus;
use App\Enums\EnrollmentSource;
use App\Enums\LessonType;
use App\Enums\QuestionType;
use App\Exceptions\AssessmentPublishException;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\User;

// InvalidArgumentException is NOT imported: a Pest file has no namespace, so
// `use InvalidArgumentException;` is a non-compound import with no effect and
// PHP emits a warning for it. The class resolves globally as it stands.

/*
|--------------------------------------------------------------------------
| Phase 8 · Author it, then sit it (Phase 8 DoD, AC-22)
|--------------------------------------------------------------------------
|
| The DoD's first line, as one test: "An admin can author a mixed-type quiz
| and a final test; a student can take both and be graded correctly."
|
| Everything below goes through the ACTIONS an admin screen actually calls,
| rather than through factories. That is the difference between proving the
| table can hold a quiz and proving the product can make one — and it is the
| path that exercises CreateAssessment, CreateQuestion, the publish validator
| and the type registry's answer-key rules, none of which had a test.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();

    $this->course = Course::factory()->published()->create(['title' => 'Astronomy']);
    $this->module = Module::factory()->forCourse($this->course)->published()->create();
    $this->quizLesson = Lesson::factory()->forModule($this->module)->published()
        ->create(['type' => LessonType::Quiz, 'title' => 'Chapter quiz']);

    $this->enrollment = app(GrantEnrollment::class)
        ->handle($this->student, $this->course, EnrollmentSource::AdminGrant, $this->admin);
});

it('lets an admin author a mixed-type quiz and a student be graded on it', function (): void {
    // ── AUTHOR ────────────────────────────────────────────────────────────
    $quiz = app(CreateAssessment::class)->handle($this->quizLesson, [
        'type' => AssessmentType::Quiz,
        'title' => 'Chapter 1 quiz',
        'passing_percentage' => 60,
    ], $this->admin);

    $single = app(CreateQuestion::class)->handle($quiz, [
        'type' => QuestionType::SingleChoice,
        'body' => 'Which planet is closest to the sun?',
        'marks' => 2,
        'options' => [
            ['body' => 'Mercury', 'is_correct' => true],
            ['body' => 'Venus', 'is_correct' => false],
        ],
    ], $this->admin);

    $multiple = app(CreateQuestion::class)->handle($quiz, [
        'type' => QuestionType::MultipleChoice,
        'body' => 'Which of these are gas giants?',
        'marks' => 3,
        'options' => [
            ['body' => 'Jupiter', 'is_correct' => true],
            ['body' => 'Saturn', 'is_correct' => true],
            ['body' => 'Mars', 'is_correct' => false],
        ],
    ], $this->admin);

    $trueFalse = app(CreateQuestion::class)->handle($quiz, [
        'type' => QuestionType::TrueFalse,
        'body' => 'The sun is a star.',
        'marks' => 1,
        'options' => [
            ['body' => 'True', 'is_correct' => true],
            ['body' => 'False', 'is_correct' => false],
        ],
    ], $this->admin);

    $shortAnswer = app(CreateQuestion::class)->handle($quiz, [
        'type' => QuestionType::ShortAnswer,
        'body' => 'Name the galaxy we live in.',
        'marks' => 4,
        'accepted_answers' => ['The Milky Way', 'Milky Way'],
    ], $this->admin);

    app(PublishAssessment::class)->handle($quiz, $this->admin);

    expect($quiz->refresh())
        ->is_published->toBeTrue()
        ->questions_count->toBe(4)
        // 2 + 3 + 1 + 4, counted by the counter service rather than by hand.
        ->and((float) $quiz->total_marks)->toBe(10.0);

    // ── SIT ───────────────────────────────────────────────────────────────
    $attempt = app(StartAttempt::class)->handle($quiz, $this->student);

    // Queried through the relation rather than read as a loaded collection —
    // preventLazyLoading is on outside production and these models came back
    // from the create action without their options.
    $pick = fn (Question $q, string $body): int => $q->options()->where('body', $body)->firstOrFail()->getKey();

    $attempt = $attempt->refresh();

    app(SaveAnswer::class)->handle($attempt, $single, [
        'selected_option_ids' => [$pick($single, 'Mercury')],
    ]);

    app(SaveAnswer::class)->handle($attempt, $multiple, [
        'selected_option_ids' => [$pick($multiple, 'Jupiter'), $pick($multiple, 'Saturn')],
    ]);

    // Deliberately wrong.
    app(SaveAnswer::class)->handle($attempt, $trueFalse, [
        'selected_option_ids' => [$pick($trueFalse, 'False')],
    ]);

    // Right, but typed casually — the grader normalises.
    app(SaveAnswer::class)->handle($attempt, $shortAnswer, ['answer_text' => 'milky way']);

    $graded = app(SubmitAttempt::class)->handle($attempt, $this->student);

    // 2 + 3 + 0 + 4 = 9 of 10.
    expect($graded->status)->toBe(AttemptStatus::Graded)
        ->and((float) $graded->score_marks)->toBe(9.0)
        ->and((float) $graded->max_marks)->toBe(10.0)
        ->and((float) $graded->score_percentage)->toBe(90.0)
        ->and($graded->is_passed)->toBeTrue();
});

it('lets an admin author a course-level final test and a student pass it', function (): void {
    $test = app(CreateAssessment::class)->handle($this->course, [
        'type' => AssessmentType::Test,
        'title' => 'Astronomy final',
        'passing_percentage' => 50,
    ], $this->admin);

    $question = app(CreateQuestion::class)->handle($test, [
        'type' => QuestionType::SingleChoice,
        'body' => 'How many planets orbit the sun?',
        'marks' => 1,
        'options' => [
            ['body' => 'Eight', 'is_correct' => true],
            ['body' => 'Nine', 'is_correct' => false],
        ],
    ], $this->admin);

    app(PublishAssessment::class)->handle($test, $this->admin);

    // A final test hangs off the COURSE, which is what distinguishes it from
    // a quiz inside a module (ADR-002) and what lets it gate completion.
    expect($test->refresh()->assessable_type)->toBe(Course::class)
        ->and($test->assessable_id)->toBe($this->course->getKey());

    $attempt = app(StartAttempt::class)->handle($test, $this->student);

    app(SaveAnswer::class)->handle($attempt, $question, [
        'selected_option_ids' => [$question->options()->where('body', 'Eight')->firstOrFail()->getKey()],
    ]);

    expect(app(SubmitAttempt::class)->handle($attempt, $this->student)->is_passed)->toBeTrue();
});

/*
| ═══════════════ THE ANSWER-KEY RULES (FR-ASMT-07) ═══════════════
*/
it('refuses a single-choice question without exactly one correct option', function (array $options): void {
    $quiz = app(CreateAssessment::class)->handle($this->quizLesson, [
        'type' => AssessmentType::Quiz, 'title' => 'Q', 'passing_percentage' => 50,
    ], $this->admin);

    // Enforced by asking the TYPE's handler, so a new question type brings its
    // own rule rather than editing the action.
    expect(fn () => app(CreateQuestion::class)->handle($quiz, [
        'type' => QuestionType::SingleChoice,
        'body' => 'Pick one',
        'marks' => 1,
        // array_values because the action's signature asks for a list, and a
        // dataset array carries no such guarantee.
        'options' => array_values($options),
    ], $this->admin))->toThrow(InvalidArgumentException::class);
})->with([
    'no correct option' => [[['body' => 'a', 'is_correct' => false], ['body' => 'b', 'is_correct' => false]]],
    'two correct options' => [[['body' => 'a', 'is_correct' => true], ['body' => 'b', 'is_correct' => true]]],
]);

it('refuses a multiple-choice question with no correct option at all', function (): void {
    $quiz = app(CreateAssessment::class)->handle($this->quizLesson, [
        'type' => AssessmentType::Quiz, 'title' => 'Q', 'passing_percentage' => 50,
    ], $this->admin);

    expect(fn () => app(CreateQuestion::class)->handle($quiz, [
        'type' => QuestionType::MultipleChoice,
        'body' => 'Pick some',
        'marks' => 1,
        'options' => [['body' => 'a', 'is_correct' => false], ['body' => 'b', 'is_correct' => false]],
    ], $this->admin))->toThrow(InvalidArgumentException::class);
});

/*
| ═══════════════ PUBLISHING (FR-ASMT-08) ═══════════════
*/
it('refuses to publish an assessment with no questions', function (): void {
    $quiz = app(CreateAssessment::class)->handle($this->quizLesson, [
        'type' => AssessmentType::Quiz, 'title' => 'Empty', 'passing_percentage' => 50,
    ], $this->admin);

    // An empty published quiz is a lesson a student can neither pass nor fail.
    expect(fn () => app(PublishAssessment::class)->handle($quiz, $this->admin))
        ->toThrow(AssessmentPublishException::class);
});

it('creates every assessment unpublished, so authoring is never live', function (): void {
    $quiz = app(CreateAssessment::class)->handle($this->quizLesson, [
        'type' => AssessmentType::Quiz, 'title' => 'Draft', 'passing_percentage' => 50,
    ], $this->admin);

    // A half-written quiz appearing to students the moment it is created is
    // the failure this default prevents.
    expect($quiz->is_published)->toBeFalse();
});

it('keeps the marks and question counters in step as questions are added', function (): void {
    $quiz = app(CreateAssessment::class)->handle($this->quizLesson, [
        'type' => AssessmentType::Quiz, 'title' => 'Counting', 'passing_percentage' => 50,
    ], $this->admin);

    expect($quiz->refresh()->questions_count)->toBe(0);

    app(CreateQuestion::class)->handle($quiz, [
        'type' => QuestionType::ShortAnswer, 'body' => 'One?', 'marks' => 5,
        'accepted_answers' => ['yes'],
    ], $this->admin);

    // The publish validator reads these counters, so a stale one would block
    // a legitimate publish or allow an empty one.
    expect($quiz->refresh())
        ->questions_count->toBe(1)
        ->and((float) $quiz->total_marks)->toBe(5.0);
});

it('numbers question positions in the order they were authored', function (): void {
    $quiz = app(CreateAssessment::class)->handle($this->quizLesson, [
        'type' => AssessmentType::Quiz, 'title' => 'Ordered', 'passing_percentage' => 50,
    ], $this->admin);

    $first = app(CreateQuestion::class)->handle($quiz, [
        'type' => QuestionType::ShortAnswer, 'body' => 'First?', 'marks' => 1, 'accepted_answers' => ['a'],
    ], $this->admin);

    $second = app(CreateQuestion::class)->handle($quiz, [
        'type' => QuestionType::ShortAnswer, 'body' => 'Second?', 'marks' => 1, 'accepted_answers' => ['b'],
    ], $this->admin);

    expect($second->position)->toBeGreaterThan($first->position);
});

it('records authoring in the audit log', function (): void {
    $quiz = app(CreateAssessment::class)->handle($this->quizLesson, [
        'type' => AssessmentType::Quiz, 'title' => 'Audited', 'passing_percentage' => 50,
    ], $this->admin);

    app(CreateQuestion::class)->handle($quiz, [
        'type' => QuestionType::ShortAnswer, 'body' => 'Q?', 'marks' => 1, 'accepted_answers' => ['a'],
    ], $this->admin);

    app(PublishAssessment::class)->handle($quiz, $this->admin);

    // Publishing changes what students can see, so it belongs in the log
    // beside the other content-visibility changes.
    expect(App\Models\AuditLog::query()->pluck('action')->all())
        ->toContain('assessment.created')
        ->toContain('question.created')
        ->toContain('assessment.published');
});

it('gives an unpublished assessment no reachable attempt path', function (): void {
    $quiz = app(CreateAssessment::class)->handle($this->quizLesson, [
        'type' => AssessmentType::Quiz, 'title' => 'Draft', 'passing_percentage' => 50,
    ], $this->admin);

    app(CreateQuestion::class)->handle($quiz, [
        'type' => QuestionType::ShortAnswer, 'body' => 'Q?', 'marks' => 1, 'accepted_answers' => ['a'],
    ], $this->admin);

    // Never published, so a student cannot reach it however they arrive.
    expect(fn () => app(StartAttempt::class)->handle($quiz->refresh(), $this->student))
        ->toThrow(App\Exceptions\AttemptNotAllowedException::class);
});

it('does not let a draft assessment be taken by guessing its id', function (): void {
    $quiz = Assessment::factory()->create([
        'assessable_type' => Course::class,
        'assessable_id' => $this->course->getKey(),
        'is_published' => false,
    ]);

    $this->actingAs($this->student)
        ->get(route('student.assessments.attempt', $quiz))
        ->assertForbidden();
});
