<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\AttemptStatus;
use App\Enums\EnrollmentSource;
use App\Enums\QuestionType;
use App\Livewire\Student\AttemptRunner;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| One question at a time (design handoff §5)
|--------------------------------------------------------------------------
|
| The runner now pages rather than listing every question. The reason this is
| safe is that ANSWERS ALREADY PERSIST ON CHANGE — so the tests that matter are
| the ones proving paging cannot lose work, cannot be pushed out of bounds by a
| forged index, and cannot grade an attempt by accident.
|
| "Save & exit" is the one with real money on it: a control labelled "save" that
| submitted would be the most expensive mislabelled button in the product.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();

    // The assessment has to hang off a course this student can open —
    // AssessmentPolicy::start refuses otherwise, which is the whole point of it.
    $this->course = Course::factory()->published()->create();

    app(GrantEnrollment::class)
        ->handle($this->student, $this->course, EnrollmentSource::AdminGrant, $this->admin);

    $this->assessment = Assessment::factory()->create([
        'assessable_type' => Course::class,
        'assessable_id' => $this->course->getKey(),
        'is_published' => true,
        'passing_percentage' => 50,
    ]);

    foreach (range(1, 3) as $n) {
        $question = Question::factory()->create([
            'assessment_id' => $this->assessment->getKey(),
            'type' => QuestionType::SingleChoice,
            'body' => "Question body {$n}",
            'marks' => 1,
            'position' => $n,
        ]);

        QuestionOption::factory()->correct()->create([
            'question_id' => $question->getKey(),
            'body' => "Right {$n}",
            'position' => 0,
        ]);
        QuestionOption::factory()->create([
            'question_id' => $question->getKey(),
            'body' => "Wrong {$n}",
            'position' => 1,
        ]);
    }

    $this->actingAs($this->student);

    /*
     * Questions are fetched by body rather than held in an array from the loop
     * above. Indexing a collection yields a nullable, and every test below that
     * needs a specific question would have to prove it exists — firstOrFail()
     * states the expectation once and fails loudly if the setup ever stops
     * producing it.
     */
    $this->questionNumber = fn (int $n): Question => Question::query()
        ->where('assessment_id', $this->assessment->getKey())
        ->where('body', "Question body {$n}")
        ->firstOrFail();
});

/*
| ═══════════════ ONE AT A TIME ═══════════════
*/
it('shows only the current question', function (): void {
    Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])
        ->assertSee('Question body 1')
        ->assertDontSee('Question body 2')
        ->assertDontSee('Question body 3');
});

it('says where the student is in the set', function (): void {
    Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])
        ->assertSee('Question 1 of 3')
        ->call('goToQuestion', 2)
        ->assertSee('Question 3 of 3');
});

it('moves forward and back', function (): void {
    Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])
        ->call('goToQuestion', 1)
        ->assertSee('Question body 2')
        ->call('goToQuestion', 0)
        ->assertSee('Question body 1');
});

it('offers submit instead of next on the last question', function (): void {
    Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])
        ->assertSee('Next question')
        ->call('goToQuestion', 2)
        ->assertSee('Submit assessment')
        ->assertDontSee('Next question');
});

it('hides Previous on the first question', function (): void {
    Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])
        ->assertDontSee('Previous')
        ->call('goToQuestion', 1)
        ->assertSee('Previous');
});

/*
| ═══════════════ A FORGED INDEX LANDS ON A REAL QUESTION ═══════════════
|
| $index is public Livewire state, so it arrives from the browser.
*/
it('clamps an index past the end', function (): void {
    Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])
        ->call('goToQuestion', 99)
        ->assertSet('index', 2)
        ->assertSee('Question body 3');
});

it('clamps a negative index', function (): void {
    Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])
        ->call('goToQuestion', -5)
        ->assertSet('index', 0)
        ->assertSee('Question body 1');
});

it('still renders when the index is set directly out of range', function (): void {
    // Bypasses goToQuestion entirely, as a tampered payload would. render()
    // clamps too, so the screen shows a real question rather than nothing.
    Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])
        ->set('index', 500)
        ->assertSee('Question body 3')
        ->assertOk();
});

/*
| ═══════════════ PAGING CANNOT LOSE WORK ═══════════════
*/
it('keeps an answer given before paging away', function (): void {
    $first = ($this->questionNumber)(1);
    $option = $first->options()->where('is_correct', true)->firstOrFail();

    Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])
        ->set("answers.{$first->id}", [$option->id])
        ->call('saveAnswer', $first->id)
        ->call('goToQuestion', 2)
        ->call('goToQuestion', 0)
        ->assertSet("answers.{$first->id}", [$option->id]);
});

it('has already persisted the answer, not just held it in the component', function (): void {
    $first = ($this->questionNumber)(1);
    $option = $first->options()->where('is_correct', true)->firstOrFail();

    Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])
        ->set("answers.{$first->id}", [$option->id])
        ->call('saveAnswer', $first->id)
        ->call('goToQuestion', 1);

    // The row exists independently of the component. This is what makes paging
    // safe, and what makes closing the tab on question 2 harmless.
    $attempt = AssessmentAttempt::query()
        ->where('assessment_id', $this->assessment->getKey())
        ->where('user_id', $this->student->getKey())
        ->firstOrFail();

    expect($attempt->answers()->where('question_id', $first->id)->exists())->toBeTrue();
});

/*
| ═══════════════ SAVE & EXIT DOES NOT SUBMIT ═══════════════
*/
it('leaves the attempt in progress on save and exit', function (): void {
    Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])->call('saveAndExit');

    $attempt = AssessmentAttempt::query()
        ->where('assessment_id', $this->assessment->getKey())
        ->where('user_id', $this->student->getKey())
        ->firstOrFail();

    // NOT graded. A "save" button that graded the attempt would end a student's
    // only remaining sitting without warning.
    expect($attempt->status)->toBe(AttemptStatus::InProgress)
        ->and($attempt->submitted_at)->toBeNull()
        ->and($attempt->score_percentage)->toBeNull();
});

it('sends the student somewhere they can resume from', function (): void {
    Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])
        ->call('saveAndExit')
        ->assertRedirect(route('student.assessments.history', $this->assessment));
});

it('resumes the same attempt rather than starting a new one', function (): void {
    Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])->call('saveAndExit');

    Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment]);

    // One attempt, not two. Stepping away must not consume an allowance.
    expect(AssessmentAttempt::query()
        ->where('assessment_id', $this->assessment->getKey())
        ->where('user_id', $this->student->getKey())
        ->count())->toBe(1);
});

/*
| ═══════════════ THE COUNT IS ANSWERED, NOT POSITION ═══════════════
*/
it('counts answered questions rather than the page number', function (): void {
    $second = ($this->questionNumber)(2);
    $option = $second->options()->firstOrFail();

    // Skipped question 1, answered question 2. Position says 2; answered says 1.
    Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])
        ->call('goToQuestion', 1)
        ->set("answers.{$second->id}", [$option->id])
        ->call('saveAnswer', $second->id)
        ->assertSee('2 still to answer');
});

it('names the unanswered count before the point of no return', function (): void {
    // "Unanswered questions score zero" is abstract. "3 of 3 not yet answered"
    // is not, and this is the last moment it can be said.
    Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])
        ->assertSee('3 of 3 questions not yet answered');
});
