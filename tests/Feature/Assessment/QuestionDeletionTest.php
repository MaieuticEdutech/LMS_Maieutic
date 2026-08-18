<?php

declare(strict_types=1);

use App\Enums\LessonType;
use App\Enums\QuestionType;
use App\Livewire\Admin\Assessments\QuestionList;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
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
| Deleting a question — as an assigned instructor
|--------------------------------------------------------------------------
|
| Reported from use: "not able to delete questions in an assessment as an
| instructor". Two separate things could cause that and they need different
| fixes, so both are pinned here — whether the instructor is refused at all,
| and whether a legitimate refusal is VISIBLE when it happens.
|
*/

beforeEach(function (): void {
    $this->instructor = User::factory()->instructor()->create();
    $this->student = User::factory()->create();

    $course = Course::factory()->published()->create();
    $course->instructors()->attach($this->instructor);

    $module = Module::factory()->forCourse($course)->published()->create();
    $lesson = Lesson::factory()->forModule($module)->published()->atPosition(0)
        ->create(['type' => LessonType::Quiz]);

    $this->assessment = Assessment::factory()->create([
        'assessable_type' => Lesson::class,
        'assessable_id' => $lesson->getKey(),
        'is_published' => false,
    ]);

    $this->makeQuestion = function (): Question {
        $question = Question::factory()->create([
            'assessment_id' => $this->assessment->getKey(),
            'type' => QuestionType::SingleChoice,
            'body' => 'Deletable question',
        ]);

        QuestionOption::factory()->correct()->create(['question_id' => $question->getKey()]);
        QuestionOption::factory()->create(['question_id' => $question->getKey()]);

        return $question;
    };
});

it('lets an assigned instructor delete an unanswered question', function (): void {
    $question = ($this->makeQuestion)();

    $this->actingAs($this->instructor);

    Livewire::test(QuestionList::class, ['assessment' => $this->assessment->refresh()])
        ->call('confirmDelete', $question->id)
        ->call('delete');

    expect(Question::query()->whereKey($question->id)->exists())->toBeFalse();
});

it('refuses to delete a question a student has already answered', function (): void {
    $question = ($this->makeQuestion)();

    $attempt = AssessmentAttempt::factory()->graded()->create([
        'assessment_id' => $this->assessment->getKey(),
        'user_id' => $this->student->getKey(),
    ]);

    AttemptAnswer::factory()->create([
        'attempt_id' => $attempt->getKey(),
        'question_id' => $question->getKey(),
    ]);

    $this->actingAs($this->instructor);

    Livewire::test(QuestionList::class, ['assessment' => $this->assessment->refresh()])
        ->call('confirmDelete', $question->id)
        ->call('delete');

    // Correct: deleting it would pull the question out from under a student's
    // graded attempt.
    expect(Question::query()->whereKey($question->id)->exists())->toBeTrue();
});

it('tells the instructor WHY the question could not be deleted', function (): void {
    /*
     * The refusal above is right; being silent about it is not.
     *
     * The action flashes to the session, and both layouts render
     * session('error') — but this is a Livewire call, and Livewire re-renders
     * the COMPONENT, not the surrounding layout. So the message is written to
     * a region that is not on screen, the row simply stays put, and the
     * instructor concludes the delete button is broken. Which is exactly what
     * was reported.
     */
    $question = ($this->makeQuestion)();

    $attempt = AssessmentAttempt::factory()->graded()->create([
        'assessment_id' => $this->assessment->getKey(),
        'user_id' => $this->student->getKey(),
    ]);

    AttemptAnswer::factory()->create([
        'attempt_id' => $attempt->getKey(),
        'question_id' => $question->getKey(),
    ]);

    $this->actingAs($this->instructor);

    Livewire::test(QuestionList::class, ['assessment' => $this->assessment->refresh()])
        ->call('confirmDelete', $question->id)
        ->call('delete')
        ->assertSee('already answered');
});

it('refuses an unassigned instructor entirely', function (): void {
    $question = ($this->makeQuestion)();

    $this->actingAs(User::factory()->instructor()->create());

    Livewire::test(QuestionList::class, ['assessment' => $this->assessment->refresh()])
        ->call('confirmDelete', $question->id)
        ->call('delete')
        ->assertForbidden();

    expect(Question::query()->whereKey($question->id)->exists())->toBeTrue();
});
