<?php

declare(strict_types=1);

use App\Enums\AssessmentType;
use App\Enums\QuestionType;
use App\Livewire\Admin\Assessments\QuestionList;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Authoring questions through the screen an author actually uses
|--------------------------------------------------------------------------
|
| ═════════════════════════════════════════════════════════════════════════
| THE "ADD QUESTION" FORM 500'd, AND NOTHING NOTICED.
|
| QuestionList::render() passed `questions` and not `questionTypes`, while the
| view read `$questionTypes`. The markup using it sits inside
| `@if ($showForm)` — false on first paint — so the page rendered perfectly
| and the error arrived only once somebody pressed the button.
|
| That is the shape of every Phase 8 defect found on this pass: the schema was
| tested, the policy was tested, and the screen an author touches was not
| exercised at all. A component test that merely renders would ALSO have
| missed this. It has to open the form.
| ═════════════════════════════════════════════════════════════════════════
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();

    $course = Course::factory()->create();
    $module = Module::factory()->forCourse($course)->create();
    $lesson = Lesson::factory()->forModule($module)->create(['type' => App\Enums\LessonType::Quiz]);

    $this->assessment = Assessment::factory()->create([
        'assessable_type' => Lesson::class,
        'assessable_id' => $lesson->getKey(),
        'type' => AssessmentType::Quiz,
        'is_published' => false,
    ]);
});

it('opens the add-question form without erroring', function (): void {
    $this->actingAs($this->admin);

    // The exact call the button makes. This is the assertion that would have
    // caught the undefined variable.
    // assertOk() last: it returns a TestResponse, so anything chained after
    // it has left Livewire's Testable behind.
    Livewire::test(QuestionList::class, ['assessment' => $this->assessment])
        ->call('openCreate')
        ->assertHasNoErrors()
        ->assertOk();
});

it('offers every registered question type in the form', function (): void {
    $this->actingAs($this->admin);

    $component = Livewire::test(QuestionList::class, ['assessment' => $this->assessment])
        ->call('openCreate');

    // Sourced from QuestionTypeRegistry, so a type with no handler can never
    // be offered to an author (ADR-003 applied to questions).
    foreach (QuestionType::cases() as $type) {
        $component->assertSee($type->value);
    }
});

it('adds a question of the chosen type', function (QuestionType $type): void {
    $this->actingAs($this->admin);

    Livewire::test(QuestionList::class, ['assessment' => $this->assessment])
        ->call('openCreate')
        ->set('type', $type->value)
        ->call('save')
        ->assertHasNoErrors();

    $question = Question::query()->where('assessment_id', $this->assessment->getKey())->firstOrFail();

    expect($question->type)->toBe($type);
})->with([
    'single choice' => QuestionType::SingleChoice,
    'multiple choice' => QuestionType::MultipleChoice,
    'true or false' => QuestionType::TrueFalse,
    'short answer' => QuestionType::ShortAnswer,
]);

it('gives a new choice question something to edit rather than an empty list', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(QuestionList::class, ['assessment' => $this->assessment])
        ->call('openCreate')
        ->set('type', QuestionType::SingleChoice->value)
        ->call('save');

    $question = Question::query()->firstOrFail();

    // A question created with no options would open an editor with nothing in
    // it and no obvious way forward.
    expect($question->options()->count())->toBe(2)
        ->and($question->options()->where('is_correct', true)->count())->toBe(1);
});

it('gives true/false its fixed pair', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(QuestionList::class, ['assessment' => $this->assessment])
        ->call('openCreate')
        ->set('type', QuestionType::TrueFalse->value)
        ->call('save');

    $bodies = Question::query()->firstOrFail()->options()->orderBy('position')->pluck('body')->all();

    expect($bodies)->toBe(['True', 'False']);
});

it('refuses a question type that does not exist', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(QuestionList::class, ['assessment' => $this->assessment])
        ->call('openCreate')
        ->set('type', 'essay')
        ->call('save')
        ->assertHasErrors('type');

    expect(Question::query()->count())->toBe(0);
});

it('keeps the counters in step as questions are added', function (): void {
    $this->actingAs($this->admin);

    $component = Livewire::test(QuestionList::class, ['assessment' => $this->assessment]);

    foreach ([QuestionType::SingleChoice, QuestionType::TrueFalse] as $type) {
        $component->call('openCreate')->set('type', $type->value)->call('save');
    }

    // The publish validator reads these, so a stale counter blocks a
    // legitimate publish or permits an empty one.
    expect($this->assessment->refresh()->questions_count)->toBe(2);
});

/*
| ═══════════════ WHO MAY AUTHOR ═══════════════
*/
it('refuses a student outright', function (): void {
    $this->actingAs(User::factory()->student()->create());

    Livewire::test(QuestionList::class, ['assessment' => $this->assessment])
        ->call('openCreate')
        ->assertForbidden();
});

it('refuses an instructor who is not assigned to the course', function (): void {
    $this->actingAs(User::factory()->instructor()->create());

    Livewire::test(QuestionList::class, ['assessment' => $this->assessment])
        ->call('openCreate')
        ->assertForbidden();
});
