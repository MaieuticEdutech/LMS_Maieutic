<?php

declare(strict_types=1);

use App\Actions\Assessment\SaveAnswer;
use App\Actions\Assessment\StartAttempt;
use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\EnrollmentSource;
use App\Enums\LessonType;
use App\Enums\QuestionType;
use App\Livewire\Student\AttemptRunner;
use App\Models\Assessment;
use App\Models\AttemptAnswer;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| QA PROBE — minimal reproduction: can a student record an answer at all?
|--------------------------------------------------------------------------
|
| Driven through the real Livewire component, i.e. exactly what a browser
| does, so the result cannot be dismissed as a test-harness artefact.
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

    $this->question = Question::factory()->create([
        'assessment_id' => $this->assessment->getKey(),
        'type' => QuestionType::SingleChoice,
        'body' => 'Pick one',
        'marks' => 1,
    ]);
    $this->correctOption = QuestionOption::factory()->correct()
        ->create(['question_id' => $this->question->getKey(), 'body' => 'right']);
    QuestionOption::factory()->create(['question_id' => $this->question->getKey(), 'body' => 'wrong']);

    $this->assessment->refresh();

    app(GrantEnrollment::class)->handle($this->student, $course, EnrollmentSource::AdminGrant, $admin);

    $this->actingAs($this->student);
});

it('records a students first answer through the attempt runner component', function (): void {
    Livewire::test(AttemptRunner::class, ['assessment' => $this->assessment])
        ->set("answers.{$this->question->id}", [$this->correctOption->id])
        ->call('saveAnswer', $this->question->id);

    expect(AttemptAnswer::query()->count())->toBe(1);
});

it('records a students first answer through the SaveAnswer action', function (): void {
    $attempt = app(StartAttempt::class)->handle($this->assessment, $this->student);

    app(SaveAnswer::class)->handle($attempt, $this->question, [
        'selected_option_ids' => [$this->correctOption->id],
    ]);

    expect(AttemptAnswer::query()->count())->toBe(1);
});
