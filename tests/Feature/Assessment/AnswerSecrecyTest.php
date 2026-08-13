<?php

declare(strict_types=1);

use App\Actions\Assessment\SubmitAttempt;
use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\AnswerRevealPolicy;
use App\Enums\AttemptStatus;
use App\Enums\EnrollmentSource;
use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use App\Services\Assessment\QuestionPresenter;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Phase 8 · The answer key stays secret (AC-23, AC-27, NFR-SEC-21)
|--------------------------------------------------------------------------
|
| ═════════════════════════════════════════════════════════════════════════
| PHASE 8's DoD SAYS "verified by test". THERE WAS NO SUCH TEST.
|
| "No assessment secret is observable in browser traffic before submission
| (verified by test)" — the DoD named the verification and it did not exist.
| QuestionOptionSchemaTest proves `is_correct` is hidden from the MODEL's
| serialisation, which is real defence in depth, but it says nothing about
| what QuestionPresenter actually hands the runner.
|
| That distinction matters: a leak here is not a bug a student reports. It is
| a bug a student uses, silently, and every score after it is fiction.
| ═════════════════════════════════════════════════════════════════════════
|
| AC-27 is the other half — the reveal policy has three modes and each one had
| to be honoured before the review screen could be trusted.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();
    $this->course = Course::factory()->published()->create();

    $this->enrollment = app(GrantEnrollment::class)
        ->handle($this->student, $this->course, EnrollmentSource::AdminGrant, $this->admin);

    $this->presenter = app(QuestionPresenter::class);

    $this->assessment = fn (AnswerRevealPolicy $reveal = AnswerRevealPolicy::AfterSubmit): Assessment => Assessment::factory()->create([
        'assessable_type' => Course::class,
        'assessable_id' => $this->course->getKey(),
        'answer_reveal' => $reveal,
        'passing_percentage' => 50,
    ]);

    $this->choiceQuestion = function (Assessment $assessment): Question {
        $question = Question::factory()->create([
            'assessment_id' => $assessment->getKey(),
            'type' => QuestionType::SingleChoice,
            'explanation' => 'Because the capital moved in 1923.',
            'marks' => 1,
            'position' => 0,
        ]);

        QuestionOption::factory()->correct()->create([
            'question_id' => $question->getKey(), 'body' => 'The right one', 'position' => 0,
        ]);
        QuestionOption::factory()->create([
            'question_id' => $question->getKey(), 'body' => 'A distractor', 'position' => 1,
        ]);

        $question->load('options');

        return $question;
    };

    $this->shortAnswerQuestion = fn (Assessment $assessment): Question => Question::factory()->create([
        'assessment_id' => $assessment->getKey(),
        'type' => QuestionType::ShortAnswer,
        'marks' => 1,
        'position' => 1,
        'meta' => ['accepted_answers' => ['Ankara']],
    ]);

    $this->attempt = function (Assessment $assessment, ?bool $passed = null): AssessmentAttempt {
        $attempt = new AssessmentAttempt;

        $attempt->forceFill([
            'ulid' => (string) Str::ulid(),
            'assessment_id' => $assessment->getKey(),
            'user_id' => $this->student->getKey(),
            'enrollment_id' => $this->enrollment->getKey(),
            'attempt_number' => 1,
            'status' => $passed === null ? AttemptStatus::InProgress : AttemptStatus::Graded,
            'started_at' => now(),
            'is_passed' => $passed,
            'question_order' => $assessment->questions()->pluck('id')->all(),
        ])->save();

        return $attempt;
    };
});

/*
| ═══════════════ AC-23 — NOTHING LEAKS BEFORE SUBMISSION ═══════════════
*/
it('hands the runner no correctness flag on any option', function (): void {
    $assessment = ($this->assessment)();
    $payload = $this->presenter->forAttempt(($this->choiceQuestion)($assessment));

    foreach ($payload['options'] as $option) {
        expect($option)->not->toHaveKey('is_correct');
    }
});

it('hands the runner no accepted answers for a short-answer question', function (): void {
    $assessment = ($this->assessment)();
    $payload = $this->presenter->forAttempt(($this->shortAnswerQuestion)($assessment));

    // The answer key for this type lives in `meta`, which must never travel
    // with the question a student is looking at.
    expect($payload)->not->toHaveKey('accepted_answers')
        ->and($payload)->not->toHaveKey('meta');
});

it('hands the runner no explanation before submission', function (): void {
    $assessment = ($this->assessment)();
    $payload = $this->presenter->forAttempt(($this->choiceQuestion)($assessment));

    // An explanation usually gives the answer away in prose.
    expect($payload)->not->toHaveKey('explanation');
});

it('leaks nothing even when the assessment reveals everything afterwards', function (): void {
    /*
     * The reveal policy governs the REVIEW screen. It must never weaken what
     * the runner sends, or "reveal after submit" would mean "reveal always"
     * to anyone reading the page source.
     */
    $assessment = ($this->assessment)(AnswerRevealPolicy::AfterSubmit);
    $payload = $this->presenter->forAttempt(($this->choiceQuestion)($assessment));

    $serialised = json_encode($payload);

    expect($serialised)->not->toContain('is_correct')
        ->and($serialised)->not->toContain('accepted_answers');
});

it('keeps the key out of the whole serialised question, however deeply nested', function (): void {
    $assessment = ($this->assessment)();
    $question = ($this->choiceQuestion)($assessment);

    // Belt and braces alongside the presenter: if a future screen ever reaches
    // for the model directly, the model itself still refuses.
    expect(json_encode($question->toArray()))->not->toContain('is_correct');
});

/*
| ═══════════════ AC-27 — THE THREE REVEAL MODES ═══════════════
*/
it('never reveals under the never policy, even on a pass', function (): void {
    $assessment = ($this->assessment)(AnswerRevealPolicy::Never);
    $question = ($this->choiceQuestion)($assessment);
    $attempt = ($this->attempt)($assessment, passed: true);

    $payload = $this->presenter->forReview($question, $attempt);

    expect($payload)->not->toHaveKey('explanation');

    foreach ($payload['options'] as $option) {
        expect($option)->not->toHaveKey('is_correct');
    }
});

it('reveals to everyone once graded under the after-submit policy', function (bool $passed): void {
    $assessment = ($this->assessment)(AnswerRevealPolicy::AfterSubmit);
    $question = ($this->choiceQuestion)($assessment);
    $attempt = ($this->attempt)($assessment, passed: $passed);

    $payload = $this->presenter->forReview($question, $attempt);

    // Including a student who failed — seeing what the right answer was is
    // the point of reviewing a paper you did badly on.
    expect($payload['explanation'])->toBe('Because the capital moved in 1923.')
        // Asserted against the serialised payload, which is what actually
        // reaches the browser.
        ->and(json_encode($payload))->toContain('"is_correct":true');
})->with([
    'a student who passed' => [true],
    'a student who failed' => [false],
]);

it('reveals only to a student who passed under the after-pass policy', function (bool $passed, bool $reveals): void {
    $assessment = ($this->assessment)(AnswerRevealPolicy::AfterPass);
    $question = ($this->choiceQuestion)($assessment);
    $attempt = ($this->attempt)($assessment, passed: $passed);

    $payload = $this->presenter->forReview($question, $attempt);

    /*
     * The mode that exists because retakes are allowed: a student who failed
     * must not be shown the key and then given another attempt at the same
     * paper.
     */
    expect(array_key_exists('explanation', $payload))->toBe($reveals);
})->with([
    'passed — may see the key' => [true, true],
    'failed — may not, there is a retake ahead' => [false, false],
]);

it('reveals accepted answers for short answer only when the policy allows', function (AnswerRevealPolicy $policy, bool $reveals): void {
    $assessment = ($this->assessment)($policy);
    $question = ($this->shortAnswerQuestion)($assessment);
    $attempt = ($this->attempt)($assessment, passed: true);

    $payload = $this->presenter->forReview($question, $attempt);

    expect(array_key_exists('accepted_answers', $payload))->toBe($reveals);
})->with([
    'never' => [AnswerRevealPolicy::Never, false],
    'after submit' => [AnswerRevealPolicy::AfterSubmit, true],
    'after pass' => [AnswerRevealPolicy::AfterPass, true],
]);

it('treats an ungraded attempt as not yet passed under after-pass', function (): void {
    $assessment = ($this->assessment)(AnswerRevealPolicy::AfterPass);
    $question = ($this->choiceQuestion)($assessment);

    // is_passed is null until grading. Null must read as "not passed", not as
    // "unknown, so allow it".
    $attempt = ($this->attempt)($assessment);

    expect($this->presenter->mayReveal($attempt))->toBeFalse()
        ->and($this->presenter->forReview($question, $attempt))->not->toHaveKey('explanation');
});

/*
| ═══════════════ END TO END ═══════════════
*/
it('goes from hidden to revealed across a real submission', function (): void {
    $assessment = ($this->assessment)(AnswerRevealPolicy::AfterSubmit);
    $question = ($this->choiceQuestion)($assessment);
    $attempt = ($this->attempt)($assessment);

    expect($this->presenter->forAttempt($question))->not->toHaveKey('explanation');

    app(SubmitAttempt::class)->handle($attempt, $this->student);

    expect($this->presenter->forReview($question, $attempt->refresh()))->toHaveKey('explanation');
});
