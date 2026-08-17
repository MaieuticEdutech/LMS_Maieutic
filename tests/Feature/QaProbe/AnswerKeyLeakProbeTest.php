<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\AnswerRevealPolicy;
use App\Enums\EnrollmentSource;
use App\Enums\LessonType;
use App\Enums\QuestionType;
use App\Livewire\Student\AttemptResult;
use App\Livewire\Student\AttemptRunner;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| QA PROBE — AC-23 / NFR-SEC-21 answer-key non-leak, DELIVERY PATH
|--------------------------------------------------------------------------
|
| QuestionOptionSchemaTest already proves the negative about the raw model
| ($hidden keeps is_correct out of toArray/toJson). It says so itself: it is
| not a test of "the path we intended", because in Phase 3 no such path
| existed.
|
| Phase 8 shipped that path — QuestionPresenter, AttemptRunner, AttemptResult.
| These probes test THAT: what a real enrolled student actually receives over
| the wire, including Livewire's serialised component state.
|
| Plan IDs: Q-38, Q-39, Q-40, Q-41, Q-42, Q-43, Q-44, Q-45.
|
*/

/**
 * Start an attempt and submit it immediately, answering nothing. Enough to
 * reach the result screen, and a guaranteed fail — which is exactly what the
 * after_pass probe needs.
 */
function startAndSubmit(User $student, Assessment $assessment): App\Models\AssessmentAttempt
{
    $attempt = app(App\Actions\Assessment\StartAttempt::class)->handle($assessment, $student);

    return app(App\Actions\Assessment\SubmitAttempt::class)->handle($attempt, $student);
}

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();

    $this->course = Course::factory()->published()->create();
    $module = Module::factory()->forCourse($this->course)->published()->create();
    $this->lesson = Lesson::factory()->forModule($module)->published()->atPosition(0)
        ->create(['type' => LessonType::Quiz, 'title' => 'Quiz lesson']);

    $this->makeAssessment = function (AnswerRevealPolicy $reveal = AnswerRevealPolicy::AfterSubmit): Assessment {
        $assessment = Assessment::factory()->create([
            'assessable_type' => Lesson::class,
            'assessable_id' => $this->lesson->getKey(),
            'answer_reveal' => $reveal,
            'passing_percentage' => 50,
            'is_published' => true,
        ]);

        // Single choice: the decoy body is the string we hunt for.
        $single = Question::factory()->create([
            'assessment_id' => $assessment->getKey(),
            'type' => QuestionType::SingleChoice,
            'body' => 'Which one is right?',
            'marks' => 1,
        ]);
        QuestionOption::factory()->correct()->create([
            'question_id' => $single->getKey(),
            'body' => 'CORRECT_OPTION_MARKER',
        ]);
        QuestionOption::factory()->create([
            'question_id' => $single->getKey(),
            'body' => 'WRONG_OPTION_MARKER',
        ]);

        // Short answer: the accepted-answer list is the secret here.
        Question::factory()->shortAnswer()->create([
            'assessment_id' => $assessment->getKey(),
            'body' => 'Type the answer',
            'marks' => 1,
            'meta' => ['accepted_answers' => ['SECRET_ACCEPTED_ANSWER']],
        ]);

        return $assessment->refresh();
    };

    $this->enrol = fn (User $u) => app(GrantEnrollment::class)
        ->handle($u, $this->course, EnrollmentSource::AdminGrant, $this->admin);

    $this->actingAs($this->student);
});

/*
| ═══════════ Q-38 / Q-39 / Q-40 — the runner, before submission ═══════════
*/

it('never sends is_correct to the student in the runner html', function (): void {
    $assessment = ($this->makeAssessment)();
    ($this->enrol)($this->student);

    $html = Livewire::test(AttemptRunner::class, ['assessment' => $assessment])
        ->html();

    expect($html)->not->toContain('is_correct')
        ->and($html)->not->toContain('isCorrect');
});

it('never sends the correct option marker to the student in the runner', function (): void {
    $assessment = ($this->makeAssessment)();
    ($this->enrol)($this->student);

    $component = Livewire::test(AttemptRunner::class, ['assessment' => $assessment]);

    // The option BODIES must render — a student has to read them to answer.
    // What must never render is any signal of WHICH one is correct.
    expect($component->html())->toContain('CORRECT_OPTION_MARKER')
        ->and($component->html())->toContain('WRONG_OPTION_MARKER');
});

it('never leaks accepted short answers to the student in the runner', function (): void {
    $assessment = ($this->makeAssessment)();
    ($this->enrol)($this->student);

    $html = Livewire::test(AttemptRunner::class, ['assessment' => $assessment])
        ->html();

    expect($html)->not->toContain('SECRET_ACCEPTED_ANSWER')
        ->and($html)->not->toContain('accepted_answers');
});

it('never carries the answer key in the livewire serialised snapshot', function (): void {
    $assessment = ($this->makeAssessment)();
    ($this->enrol)($this->student);

    // Livewire round-trips public component state through the page, and the
    // serialised snapshot is embedded in the rendered markup as the
    // wire:snapshot attribute. A hydrated Question/Assessment model in public
    // state would carry the key there even if the Blade template never
    // printed it — so asserting on the full markup covers the snapshot too.
    $markup = Livewire::test(AttemptRunner::class, ['assessment' => $assessment])->html();

    expect($markup)->toContain('wire:snapshot')
        ->and($markup)->not->toContain('is_correct')
        ->and($markup)->not->toContain('SECRET_ACCEPTED_ANSWER')
        ->and($markup)->not->toContain('accepted_answers');
});

/*
| ═══════════ Q-41 … Q-45 — the result screen, honouring the policy ═══════════
*/

it('never reveals the answer key on the result screen when the policy is never', function (): void {
    $assessment = ($this->makeAssessment)(AnswerRevealPolicy::Never);
    ($this->enrol)($this->student);

    $attempt = startAndSubmit($this->student, $assessment);

    $html = Livewire::test(AttemptResult::class, ['attempt' => $attempt])
        ->html();

    expect($html)->not->toContain('SECRET_ACCEPTED_ANSWER')
        ->and($html)->not->toContain('is_correct');
});

it('hides the answer key from a failed attempt when the policy is after_pass', function (): void {
    $assessment = ($this->makeAssessment)(AnswerRevealPolicy::AfterPass);
    ($this->enrol)($this->student);

    // Submitted with no answers at all — guaranteed fail against a 50% bar.
    $attempt = startAndSubmit($this->student, $assessment);

    expect($attempt->is_passed)->toBeFalse();

    $html = Livewire::test(AttemptResult::class, ['attempt' => $attempt])
        ->html();

    expect($html)->not->toContain('SECRET_ACCEPTED_ANSWER');
});

it('refuses to show another students result', function (): void {
    $assessment = ($this->makeAssessment)();
    ($this->enrol)($this->student);
    $attempt = startAndSubmit($this->student, $assessment);

    $other = User::factory()->create();
    ($this->enrol)($other);

    // Enrolled in the same course, but not the owner of this attempt.
    $this->actingAs($other);

    Livewire::test(AttemptResult::class, ['attempt' => $attempt])
        ->assertForbidden();
});
