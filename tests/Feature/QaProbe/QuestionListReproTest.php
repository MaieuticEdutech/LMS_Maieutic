<?php

declare(strict_types=1);

use App\Enums\LessonType;
use App\Enums\QuestionType;
use App\Livewire\Admin\Assessments\QuestionList;
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
| QA PROBE — minimal reproduction: QuestionList's missing $questionTypes
|--------------------------------------------------------------------------
|
| render() passes only 'questions'; the Blade template also reads
| $questionTypes (lines 27 and 60). Two independent trigger paths.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();

    $course = Course::factory()->published()->create();
    $module = Module::factory()->forCourse($course)->published()->create();
    $lesson = Lesson::factory()->forModule($module)->published()->atPosition(0)
        ->create(['type' => LessonType::Quiz]);

    $this->assessment = Assessment::factory()->create([
        'assessable_type' => Lesson::class,
        'assessable_id' => $lesson->getKey(),
        'is_published' => false,
    ]);

    $this->actingAs($this->admin);
});

it('renders an empty question list with the form closed', function (): void {
    Livewire::test(QuestionList::class, ['assessment' => $this->assessment])
        ->assertOk();
});

it('renders the question list when the assessment has one question', function (): void {
    $question = Question::factory()->create([
        'assessment_id' => $this->assessment->getKey(),
        'type' => QuestionType::SingleChoice,
        'marks' => 1,
    ]);
    QuestionOption::factory()->correct()->create(['question_id' => $question->getKey()]);

    Livewire::test(QuestionList::class, ['assessment' => $this->assessment->refresh()])
        ->assertOk();
});

it('renders the add-question form on an empty assessment', function (): void {
    Livewire::test(QuestionList::class, ['assessment' => $this->assessment])
        ->call('openCreate')
        ->assertOk();
});
