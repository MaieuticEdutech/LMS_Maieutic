<?php

declare(strict_types=1);

use App\Enums\AnswerRevealPolicy;
use App\Enums\LessonType;
use App\Enums\QuestionType;
use App\Enums\ScoringPolicy;
use App\Livewire\Admin\Assessments\AssessmentBuilder;
use App\Livewire\Admin\Assessments\QuestionEditor;
use App\Livewire\Instructor\Assessments\Results;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| QA PROBE — assessment authoring, publish gating and instructor scoping
|--------------------------------------------------------------------------
|
| Plan IDs: ASM-11 … ASM-33, Q-02 … Q-22, I-38, I-41, I-47, RBAC-S11 … S13,
| RBAC-X02, RBAC-X03.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->instructor = User::factory()->instructor()->create();
    $this->otherInstructor = User::factory()->instructor()->create();
    $this->student = User::factory()->create();

    $this->course = Course::factory()->published()->create();
    $this->course->instructors()->attach($this->instructor);

    $module = Module::factory()->forCourse($this->course)->published()->create();
    $this->lesson = Lesson::factory()->forModule($module)->published()->atPosition(0)
        ->create(['type' => LessonType::Quiz]);

    $this->assessment = Assessment::factory()->create([
        'assessable_type' => Lesson::class,
        'assessable_id' => $this->lesson->getKey(),
        'is_published' => false,
        'passing_percentage' => 50,
    ]);

    // Default actor for the Livewire cases below; the route-level cases set
    // their own actor explicitly via $this->actingAs().
    $this->actingAs($this->admin);
});

/*
| ═══════════ ASM-13 … ASM-24 — settings validation ═══════════
*/

it('rejects an out of range passing percentage', function (int $value): void {
    Livewire::test(AssessmentBuilder::class, ['assessment' => $this->assessment])
        ->set('passing_percentage', $value)
        ->call('save')
        ->assertHasErrors('passing_percentage');
})->with([-1, 101, 500]);

it('accepts the passing percentage boundaries', function (int $value): void {
    Livewire::test(AssessmentBuilder::class, ['assessment' => $this->assessment])
        ->set('passing_percentage', $value)
        ->call('save')
        ->assertHasNoErrors('passing_percentage');
})->with([0, 100]);

it('rejects a zero or negative time limit', function (int $value): void {
    Livewire::test(AssessmentBuilder::class, ['assessment' => $this->assessment])
        ->set('time_limit_minutes', $value)
        ->call('save')
        ->assertHasErrors('time_limit_minutes');
})->with([0, -30]);

it('rejects a zero max_attempts but allows null', function (): void {
    $component = Livewire::test(AssessmentBuilder::class, ['assessment' => $this->assessment]);

    $component->set('max_attempts', 0)->call('save')->assertHasErrors('max_attempts');
    $component->set('max_attempts', null)->call('save')->assertHasNoErrors('max_attempts');
});

it('rejects a tampered scoring policy', function (): void {
    Livewire::test(AssessmentBuilder::class, ['assessment' => $this->assessment])
        ->set('scoring_policy', 'average')
        ->call('save')
        ->assertHasErrors('scoring_policy');
});

it('rejects a tampered answer reveal policy', function (): void {
    Livewire::test(AssessmentBuilder::class, ['assessment' => $this->assessment])
        ->set('answer_reveal', 'sometimes')
        ->call('save')
        ->assertHasErrors('answer_reveal');
});

it('accepts every legal scoring policy', function (): void {
    foreach (ScoringPolicy::values() as $policy) {
        Livewire::test(AssessmentBuilder::class, ['assessment' => $this->assessment])
            ->set('scoring_policy', $policy)
            ->call('save')
            ->assertHasNoErrors('scoring_policy');
    }
});

it('accepts every legal answer reveal policy', function (): void {
    foreach (AnswerRevealPolicy::values() as $policy) {
        Livewire::test(AssessmentBuilder::class, ['assessment' => $this->assessment])
            ->set('answer_reveal', $policy)
            ->call('save')
            ->assertHasNoErrors('answer_reveal');
    }
});

it('rejects an empty title and an overlong one', function (): void {
    $component = Livewire::test(AssessmentBuilder::class, ['assessment' => $this->assessment]);

    $component->set('title', '')->call('save')->assertHasErrors('title');
    $component->set('title', str_repeat('a', 256))->call('save')->assertHasErrors('title');
    $component->set('title', str_repeat('a', 255))->call('save')->assertHasNoErrors('title');
});

/*
| ═══════════ ASM-29 … ASM-33 — publish gating ═══════════
*/

it('blocks publishing an assessment with no questions', function (): void {
    Livewire::test(AssessmentBuilder::class, ['assessment' => $this->assessment])
        ->call('publish');

    expect($this->assessment->refresh()->is_published)->toBeFalse();
});

it('allows publishing once a marked question exists', function (): void {
    // Through the action, not the factory: CreateQuestion is what maintains
    // total_marks and questions_count, and the publish validator reads those.
    // A factory-built question leaves total_marks at zero and is correctly
    // refused.
    app(App\Actions\Assessment\CreateQuestion::class)->handle($this->assessment, [
        'type' => QuestionType::SingleChoice,
        'body' => 'Which option is right?',
        'marks' => 5,
        'options' => [
            ['body' => 'The right one', 'is_correct' => true],
            ['body' => 'The wrong one', 'is_correct' => false],
        ],
    ], $this->admin);

    Livewire::test(AssessmentBuilder::class, ['assessment' => $this->assessment->refresh()])
        ->call('publish');

    expect($this->assessment->refresh()->is_published)->toBeTrue();
});

/*
| ═══════════ Q-04 … Q-22 — question validation ═══════════
*/

it('rejects zero or negative marks on a question', function (float $marks): void {
    $question = Question::factory()->create([
        'assessment_id' => $this->assessment->getKey(),
        'type' => QuestionType::SingleChoice,
    ]);
    QuestionOption::factory()->correct()->create(['question_id' => $question->getKey()]);
    QuestionOption::factory()->create(['question_id' => $question->getKey()]);

    Livewire::test(QuestionEditor::class, ['question' => $question->refresh()])
        ->set('marks', $marks)
        ->call('save')
        ->assertHasErrors('marks');
})->with([0, -1]);

it('rejects negative negative_marks', function (): void {
    $question = Question::factory()->create([
        'assessment_id' => $this->assessment->getKey(),
        'type' => QuestionType::SingleChoice,
    ]);
    QuestionOption::factory()->correct()->create(['question_id' => $question->getKey()]);
    QuestionOption::factory()->create(['question_id' => $question->getKey()]);

    Livewire::test(QuestionEditor::class, ['question' => $question->refresh()])
        ->set('negative_marks', -1)
        ->call('save')
        ->assertHasErrors('negative_marks');
});

it('rejects an overlong question body', function (): void {
    $question = Question::factory()->create([
        'assessment_id' => $this->assessment->getKey(),
        'type' => QuestionType::SingleChoice,
    ]);
    QuestionOption::factory()->correct()->create(['question_id' => $question->getKey()]);
    QuestionOption::factory()->create(['question_id' => $question->getKey()]);

    Livewire::test(QuestionEditor::class, ['question' => $question->refresh()])
        ->set('body', str_repeat('x', 2001))
        ->call('save')
        ->assertHasErrors('body');
});

it('keeps exactly one option correct on a single choice question', function (): void {
    $question = Question::factory()->create([
        'assessment_id' => $this->assessment->getKey(),
        'type' => QuestionType::SingleChoice,
    ]);
    QuestionOption::factory()->correct()->create(['question_id' => $question->getKey()]);
    QuestionOption::factory()->create(['question_id' => $question->getKey()]);

    $component = Livewire::test(QuestionEditor::class, ['question' => $question->refresh()])
        ->call('markCorrectOption', 1);

    $options = $component->get('options');
    $correct = array_filter($options, static fn (array $o): bool => $o['is_correct'] === true);

    expect($correct)->toHaveCount(1)
        ->and(array_key_first($correct))->toBe(1);
});

/*
| ═══════════ RBAC — assessment surfaces ═══════════
*/

it('refuses a student the admin assessment builder route', function (): void {
    $this->actingAs($this->student)
        ->get(route('admin.assessments.builder', $this->assessment))
        ->assertForbidden();
});

it('refuses a student the assessment results route', function (): void {
    $this->actingAs($this->student)
        ->get(route('admin.assessments.results', $this->assessment))
        ->assertForbidden();
});

it('refuses an instructor the admin assessments index', function (): void {
    $this->actingAs($this->instructor)
        ->get(route('admin.assessments.index'))
        ->assertForbidden();
});

it('lets an assigned instructor open the builder for their own course', function (): void {
    $this->actingAs($this->instructor)
        ->get(route('instructor.assessments.builder', $this->assessment))
        ->assertOk();
});

it('refuses an unassigned instructor the builder for another course', function (): void {
    $this->actingAs($this->otherInstructor)
        ->get(route('instructor.assessments.builder', $this->assessment))
        ->assertForbidden();
});

it('refuses an unassigned instructor the results screen', function (): void {
    $this->actingAs($this->otherInstructor)
        ->get(route('instructor.assessments.results', $this->assessment))
        ->assertForbidden();
});

/*
| ═══════════ I-41 / I-43 — results statistics ═══════════
|
| Attempts are seeded through the factory rather than the runner, because
| SaveAnswer cannot record an answer (see the SaveAnswer reproduction).
*/

it('renders the results screen with no attempts without erroring', function (): void {
    $this->actingAs($this->instructor);

    Livewire::test(Results::class, ['assessment' => $this->assessment])
        ->assertOk();
});

it('renders the results screen with graded attempts', function (): void {
    $this->actingAs($this->instructor);

    AssessmentAttempt::factory()->count(3)->graded()->create([
        'assessment_id' => $this->assessment->getKey(),
    ]);

    Livewire::test(Results::class, ['assessment' => $this->assessment])
        ->assertOk();
});
