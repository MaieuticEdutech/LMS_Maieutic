<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\EnrollmentSource;
use App\Enums\LessonType;
use App\Enums\QuestionType;
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
| Multiple choice — picking one option must pick exactly one
|--------------------------------------------------------------------------
|
| Reported from use: "multiple choices — when I choose an option all of them
| get chosen".
|
| Every checkbox for a question binds to the same property, answers.{id}.
| Livewire accumulates into it as an ARRAY — but only if it already holds one.
| seedAnswers() writes a key only for questions that have already been
| answered, so on a fresh attempt the key is absent, the property is scalar,
| and checking any box makes it truthy: every checkbox bound to it then
| renders checked.
|
*/

beforeEach(function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();

    $course = Course::factory()->published()->create();
    $module = Module::factory()->forCourse($course)->published()->create();
    $lesson = Lesson::factory()->forModule($module)->published()->atPosition(0)
        ->create(['type' => LessonType::Quiz]);

    $this->assessment = Assessment::factory()->create([
        'assessable_type' => Lesson::class,
        'assessable_id' => $lesson->getKey(),
        'is_published' => true,
    ]);

    $this->question = Question::factory()->multipleChoice()->create([
        'assessment_id' => $this->assessment->getKey(),
        'body' => 'Which of these are true?',
        'marks' => 3,
    ]);

    $this->optionA = QuestionOption::factory()->correct()->create(['question_id' => $this->question->getKey(), 'body' => 'Alpha']);
    $this->optionB = QuestionOption::factory()->create(['question_id' => $this->question->getKey(), 'body' => 'Bravo']);
    $this->optionC = QuestionOption::factory()->create(['question_id' => $this->question->getKey(), 'body' => 'Charlie']);

    $this->assessment->refresh();

    app(GrantEnrollment::class)->handle($this->student, $course, EnrollmentSource::AdminGrant, $admin);

    $this->actingAs($this->student);
});

it('starts a multiple choice question with an array, not a scalar', function (): void {
    // The root cause. With no array here, every checkbox bound to
    // answers.{id} shares one scalar and they all light up together.
    $answers = Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])
        ->get('answers');

    expect($answers)->toHaveKey($this->question->id)
        ->and($answers[$this->question->id])->toBeArray();
});

it('selects only the option chosen', function (): void {
    $component = Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])
        ->set("answers.{$this->question->id}", [(string) $this->optionB->id])
        ->call('saveAnswer', $this->question->id);

    $selected = $component->get('answers')[$this->question->id];

    expect($selected)->toBeArray()
        ->and($selected)->toHaveCount(1)
        ->and((int) $selected[0])->toBe($this->optionB->id);
});

it('records only the chosen option against the attempt', function (): void {
    Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])
        ->set("answers.{$this->question->id}", [(string) $this->optionB->id])
        ->call('saveAnswer', $this->question->id);

    $stored = App\Models\AttemptAnswer::query()
        ->where('question_id', $this->question->getKey())
        ->firstOrFail();

    expect($stored->selected_option_ids)->toBe([$this->optionB->id]);
});

it('accumulates a second choice without losing the first', function (): void {
    $component = Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])
        ->set("answers.{$this->question->id}", [(string) $this->optionA->id, (string) $this->optionC->id])
        ->call('saveAnswer', $this->question->id);

    $selected = array_map('intval', $component->get('answers')[$this->question->id]);

    sort($selected);
    $expected = [$this->optionA->id, $this->optionC->id];
    sort($expected);

    expect($selected)->toBe($expected);
});

it('gives a single choice question a scalar, not an array', function (): void {
    // The mirror of the bug: a radio group must NOT be seeded with an array,
    // or nothing appears selected when one is picked.
    $single = Question::factory()->create([
        'assessment_id' => $this->assessment->getKey(),
        'type' => QuestionType::SingleChoice,
        'body' => 'Pick exactly one',
    ]);
    QuestionOption::factory()->correct()->create(['question_id' => $single->getKey()]);
    QuestionOption::factory()->create(['question_id' => $single->getKey()]);

    $answers = Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment->refresh()])
        ->get('answers');

    expect($answers[$single->id])->not->toBeArray();
});
