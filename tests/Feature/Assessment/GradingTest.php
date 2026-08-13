<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\AttemptStatus;
use App\Enums\EnrollmentSource;
use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AttemptAnswer;
use App\Models\Course;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use App\Services\Assessment\GradingService;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Phase 8 · Grading (AC-22, FR-ASMT-12)
|--------------------------------------------------------------------------
|
| ═════════════════════════════════════════════════════════════════════════
| GRADING IS WHAT THIS PRODUCT CERTIFIES, AND IT HAD NO TEST.
|
| Phase 8 shipped GradingService, four type handlers and negative marking.
| Every one of them was verified only by the schema tests around them — the
| table's constraints were proven, the arithmetic was not. A regression here
| is the most expensive kind available: it is silent, it is wrong in a
| direction nobody checks, and the student is told a number they will act on.
|
| These tests assert the arithmetic against hand-computed answers, one
| property per test, so a failure says which rule broke rather than "grading
| is wrong".
| ═════════════════════════════════════════════════════════════════════════
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();

    $this->course = Course::factory()->published()->create();

    $this->enrollment = app(GrantEnrollment::class)
        ->handle($this->student, $this->course, EnrollmentSource::AdminGrant, $this->admin);

    /** Build an assessment attached to this course. */
    $this->assessment = function (array $attributes = []): Assessment {
        return Assessment::factory()->create(array_merge([
            'assessable_type' => Course::class,
            'assessable_id' => $this->course->getKey(),
            'passing_percentage' => 70,
        ], $attributes));
    };

    /** A single-choice question with one correct option and one distractor. */
    $this->choiceQuestion = function (Assessment $assessment, int $marks = 1, int $negative = 0, int $position = 0): array {
        $question = Question::factory()->create([
            'assessment_id' => $assessment->getKey(),
            'type' => QuestionType::SingleChoice,
            'marks' => $marks,
            'negative_marks' => $negative,
            'position' => $position,
        ]);

        $right = QuestionOption::factory()->correct()->create(['question_id' => $question->getKey(), 'position' => 0]);
        $wrong = QuestionOption::factory()->create(['question_id' => $question->getKey(), 'position' => 1]);

        return [$question, $right, $wrong];
    };

    /** An in-progress attempt on the given assessment. */
    $this->attempt = function (Assessment $assessment): AssessmentAttempt {
        $attempt = new AssessmentAttempt;

        $attempt->forceFill([
            'ulid' => (string) Str::ulid(),
            'assessment_id' => $assessment->getKey(),
            'user_id' => $this->student->getKey(),
            'enrollment_id' => $this->enrollment->getKey(),
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
            'question_order' => $assessment->questions()->pluck('id')->all(),
        ])->save();

        return $attempt;
    };

    $this->answer = function (AssessmentAttempt $attempt, Question $question, ?array $optionIds = null, ?string $text = null): AttemptAnswer {
        $answer = new AttemptAnswer;

        $answer->forceFill([
            'attempt_id' => $attempt->getKey(),
            'question_id' => $question->getKey(),
            'selected_option_ids' => $optionIds,
            'answer_text' => $text,
            'answered_at' => now(),
        ])->save();

        return $answer;
    };

    // Re-read rather than reusing the in-memory model: SubmitAttempt grades a
    // row it fetched itself under a lock, so grading a stale instance here
    // would test something the application never does.
    $this->grade = fn (AssessmentAttempt $attempt): array => app(GradingService::class)
        ->grade(AssessmentAttempt::query()->whereKey($attempt->getKey())->firstOrFail());
});

/*
| ═══════════════ THE ARITHMETIC ═══════════════
*/
it('scores a fully correct attempt at a hundred per cent', function (): void {
    $assessment = ($this->assessment)();
    [$q1, $right1] = ($this->choiceQuestion)($assessment, marks: 2, position: 0);
    [$q2, $right2] = ($this->choiceQuestion)($assessment, marks: 3, position: 1);

    $attempt = ($this->attempt)($assessment);
    ($this->answer)($attempt, $q1, [$right1->getKey()]);
    ($this->answer)($attempt, $q2, [$right2->getKey()]);

    expect(($this->grade)($attempt))->toMatchArray([
        'scoreMarks' => 5.0,
        'maxMarks' => 5.0,
        'percentage' => 100.0,
        'passed' => true,
    ]);
});

it('weights each question by its own marks, not by question count', function (): void {
    // One question worth 4 and one worth 1. Getting the big one right is 80%,
    // not 50% — a per-question average would report the wrong figure and
    // change whether the student passed.
    $assessment = ($this->assessment)();
    [$big, $bigRight] = ($this->choiceQuestion)($assessment, marks: 4, position: 0);
    [$small, , $smallWrong] = ($this->choiceQuestion)($assessment, marks: 1, position: 1);

    $attempt = ($this->attempt)($assessment);
    ($this->answer)($attempt, $big, [$bigRight->getKey()]);
    ($this->answer)($attempt, $small, [$smallWrong->getKey()]);

    expect(($this->grade)($attempt))->toMatchArray([
        'scoreMarks' => 4.0,
        'maxMarks' => 5.0,
        'percentage' => 80.0,
        'passed' => true,
    ]);
});

it('counts an unanswered question against the total but never below zero', function (): void {
    $assessment = ($this->assessment)();
    [$answered, $right] = ($this->choiceQuestion)($assessment, marks: 1, position: 0);
    ($this->choiceQuestion)($assessment, marks: 1, position: 1);

    $attempt = ($this->attempt)($assessment);
    ($this->answer)($attempt, $answered, [$right->getKey()]);

    // A blank is not a wrong guess. It is worth zero, and it still enlarges
    // the denominator.
    expect(($this->grade)($attempt))->toMatchArray([
        'scoreMarks' => 1.0,
        'maxMarks' => 2.0,
        'percentage' => 50.0,
    ]);
});

it('passes only at or above the pass mark', function (int $percentage, bool $passes): void {
    $assessment = ($this->assessment)(['passing_percentage' => 70]);

    // Ten one-mark questions, so the score in marks IS the percentage.
    $questions = [];

    foreach (range(0, 9) as $i) {
        $questions[] = ($this->choiceQuestion)($assessment, marks: 1, position: $i);
    }

    $attempt = ($this->attempt)($assessment);

    foreach ($questions as $i => [$question, $right, $wrong]) {
        ($this->answer)($attempt, $question, [($i * 10) < $percentage ? $right->getKey() : $wrong->getKey()]);
    }

    expect(($this->grade)($attempt)['passed'])->toBe($passes);
})->with([
    'just below the line' => [60, false],
    'exactly on the line' => [70, true],
    'comfortably above' => [90, true],
]);

/*
| ═══════════════ NEGATIVE MARKING (AC-22) ═══════════════
|
| The setting was editable in the admin builder and stored on every
| assessment, and nothing asserted it changed a single score.
*/
it('subtracts negative marks for a wrong answer when it is enabled', function (): void {
    $assessment = ($this->assessment)(['negative_marking_enabled' => true, 'passing_percentage' => 50]);
    [$right, $rightOption] = ($this->choiceQuestion)($assessment, marks: 4, negative: 0, position: 0);
    [$wrong, , $wrongOption] = ($this->choiceQuestion)($assessment, marks: 4, negative: 2, position: 1);

    $attempt = ($this->attempt)($assessment);
    ($this->answer)($attempt, $right, [$rightOption->getKey()]);
    ($this->answer)($attempt, $wrong, [$wrongOption->getKey()]);

    // 4 earned, 2 lost = 2 of 8.
    expect(($this->grade)($attempt))->toMatchArray([
        'scoreMarks' => 2.0,
        'maxMarks' => 8.0,
        'percentage' => 25.0,
        'passed' => false,
    ]);
});

it('leaves a wrong answer at zero when negative marking is off', function (): void {
    $assessment = ($this->assessment)(['negative_marking_enabled' => false]);
    [$right, $rightOption] = ($this->choiceQuestion)($assessment, marks: 4, negative: 0, position: 0);
    [$wrong, , $wrongOption] = ($this->choiceQuestion)($assessment, marks: 4, negative: 2, position: 1);

    $attempt = ($this->attempt)($assessment);
    ($this->answer)($attempt, $right, [$rightOption->getKey()]);
    ($this->answer)($attempt, $wrong, [$wrongOption->getKey()]);

    // The per-question negative_marks are still SET. The assessment-level
    // switch is what decides whether they apply — storing a penalty is not
    // the same as charging it.
    expect(($this->grade)($attempt)['scoreMarks'])->toBe(4.0);
});

it('never negatively marks a question the student left blank', function (): void {
    $assessment = ($this->assessment)(['negative_marking_enabled' => true]);
    ($this->choiceQuestion)($assessment, marks: 4, negative: 2, position: 0);

    $attempt = ($this->attempt)($assessment);

    // Negative marking penalises a wrong guess, not a blank. Penalising
    // silence would make not-answering worse than guessing, which is the
    // opposite of what the mechanism is for.
    expect(($this->grade)($attempt)['scoreMarks'])->toBe(0.0);
});

it('floors a disastrous run at zero rather than a negative score', function (): void {
    $assessment = ($this->assessment)(['negative_marking_enabled' => true]);
    [$q1, , $w1] = ($this->choiceQuestion)($assessment, marks: 1, negative: 5, position: 0);
    [$q2, , $w2] = ($this->choiceQuestion)($assessment, marks: 1, negative: 5, position: 1);

    $attempt = ($this->attempt)($assessment);
    ($this->answer)($attempt, $q1, [$w1->getKey()]);
    ($this->answer)($attempt, $q2, [$w2->getKey()]);

    // -10 of 2. A student cannot owe marks back, and a negative percentage
    // on a result screen is nonsense.
    expect(($this->grade)($attempt))->toMatchArray([
        'scoreMarks' => 0.0,
        'percentage' => 0.0,
    ]);
});

/*
| ═══════════════ EVERY QUESTION TYPE (AC-22) ═══════════════
*/
it('grades multiple choice only on an exactly correct set', function (array $selection, bool $correct): void {
    $assessment = ($this->assessment)();

    $question = Question::factory()->multipleChoice()->create([
        'assessment_id' => $assessment->getKey(), 'marks' => 2, 'position' => 0,
    ]);

    $a = QuestionOption::factory()->correct()->create(['question_id' => $question->getKey(), 'position' => 0]);
    $b = QuestionOption::factory()->correct()->create(['question_id' => $question->getKey(), 'position' => 1]);
    $c = QuestionOption::factory()->create(['question_id' => $question->getKey(), 'position' => 2]);

    $ids = ['a' => $a->getKey(), 'b' => $b->getKey(), 'c' => $c->getKey()];

    $attempt = ($this->attempt)($assessment);
    ($this->answer)($attempt, $question, array_map(static fn (string $k): int => $ids[$k], $selection));

    expect(($this->grade)($attempt)['scoreMarks'])->toBe($correct ? 2.0 : 0.0);
})->with([
    'both correct options' => [['a', 'b'], true],
    'order does not matter' => [['b', 'a'], true],
    'a partial selection is not correct' => [['a'], false],
    'a correct set plus a distractor is not correct' => [['a', 'b', 'c'], false],
    'only a distractor' => [['c'], false],
]);

it('grades true/false', function (bool $pickTrue, bool $correct): void {
    $assessment = ($this->assessment)();

    $question = Question::factory()->trueFalse()->create([
        'assessment_id' => $assessment->getKey(), 'marks' => 1, 'position' => 0,
    ]);

    $true = QuestionOption::factory()->correct()->create(['question_id' => $question->getKey(), 'body' => 'True', 'position' => 0]);
    $false = QuestionOption::factory()->create(['question_id' => $question->getKey(), 'body' => 'False', 'position' => 1]);

    $attempt = ($this->attempt)($assessment);
    ($this->answer)($attempt, $question, [$pickTrue ? $true->getKey() : $false->getKey()]);

    expect(($this->grade)($attempt)['scoreMarks'])->toBe($correct ? 1.0 : 0.0);
})->with([
    'the true option' => [true, true],
    'the false option' => [false, false],
]);

it('grades short answer against accepted answers, forgiving case and spacing', function (string $given, bool $correct): void {
    $assessment = ($this->assessment)();

    $question = Question::factory()->create([
        'assessment_id' => $assessment->getKey(),
        'type' => QuestionType::ShortAnswer,
        'marks' => 1,
        'position' => 0,
        'meta' => ['accepted_answers' => ['Paris', 'City of Paris']],
    ]);

    $attempt = ($this->attempt)($assessment);
    ($this->answer)($attempt, $question, text: $given);

    /*
     * Normalisation is a fairness decision, not laxity: a student who typed
     * the right answer with a capital in the wrong place has demonstrated the
     * knowledge the question asked about. What it must NOT do is accept a
     * different answer.
     */
    expect(($this->grade)($attempt)['scoreMarks'])->toBe($correct ? 1.0 : 0.0);
})->with([
    'exact' => ['Paris', true],
    'different case' => ['paris', true],
    'padded with spaces' => ['  Paris  ', true],
    'collapsed inner spacing' => ['City   of   Paris', true],
    'the second accepted answer' => ['city of paris', true],
    'a different answer' => ['London', false],
    'blank' => ['', false],
    'a near miss is still a miss' => ['Pariss', false],
]);

/*
| ═══════════════ WHAT GRADING WRITES BACK ═══════════════
*/
it('records per-question correctness and marks on every answered row', function (): void {
    $assessment = ($this->assessment)(['negative_marking_enabled' => true]);
    [$right, $rightOption] = ($this->choiceQuestion)($assessment, marks: 3, negative: 1, position: 0);
    [$wrong, , $wrongOption] = ($this->choiceQuestion)($assessment, marks: 3, negative: 1, position: 1);

    $attempt = ($this->attempt)($assessment);
    $rightAnswer = ($this->answer)($attempt, $right, [$rightOption->getKey()]);
    $wrongAnswer = ($this->answer)($attempt, $wrong, [$wrongOption->getKey()]);

    ($this->grade)($attempt);

    // The review screen is built from these rows. An aggregate score with no
    // per-question detail behind it cannot be explained to a student who asks.
    expect($rightAnswer->refresh())
        ->is_correct->toBeTrue()
        ->marks_awarded->toEqual(3.0);

    expect($wrongAnswer->refresh())
        ->is_correct->toBeFalse()
        ->marks_awarded->toEqual(-1.0);
});

it('reports nought rather than dividing by zero on an assessment with no questions', function (): void {
    $attempt = ($this->attempt)(($this->assessment)());

    expect(($this->grade)($attempt))->toMatchArray([
        'scoreMarks' => 0.0,
        'maxMarks' => 0.0,
        'percentage' => 0.0,
    ]);
});
