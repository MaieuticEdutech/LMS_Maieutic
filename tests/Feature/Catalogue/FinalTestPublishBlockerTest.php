<?php

declare(strict_types=1);

use App\Enums\AssessmentType;
use App\Enums\LessonType;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\MediaFile;
use App\Models\Module;
use App\Services\Content\CoursePublishValidator;

/*
|--------------------------------------------------------------------------
| A course that requires a final test must have one (AC-31, FR-PROG-08)
|--------------------------------------------------------------------------
|
| Found in use: a course was published with requires_final_test set and no
| final test authored. ProgressCalculator's gate is fail-safe, so every
| student reached 100% of lessons and the course never completed — and no
| certificate was ever issued. Correct behaviour, invisible cause.
|
| Publishing is the moment to catch it: the last point at which the author is
| still looking at the course.
|
*/

beforeEach(function (): void {
    // A course that is otherwise ready to publish, so these tests isolate the
    // final-test rule rather than tripping on a missing thumbnail.
    $this->course = Course::factory()->create([
        'description' => 'A complete description.',
        'price_amount' => 50000,
        'requires_final_test' => false,
    ]);

    MediaFile::factory()->create([
        'attachable_type' => Course::class,
        'attachable_id' => $this->course->getKey(),
    ]);

    $module = Module::factory()->forCourse($this->course)->published()->create();
    Lesson::factory()->forModule($module)->published()->atPosition(0)
        ->create(['type' => LessonType::Text, 'body' => '<p>Content.</p>']);

    $this->course->refresh();
});

it('is ready to publish when no final test is required', function (): void {
    expect(app(CoursePublishValidator::class)->blockers($this->course))->toBe([]);
});

it('blocks publishing when a final test is required but missing', function (): void {
    $this->course->forceFill(['requires_final_test' => true])->save();

    $blockers = app(CoursePublishValidator::class)->blockers($this->course->refresh());

    expect($blockers)->not->toBeEmpty()
        ->and(implode(' ', $blockers))->toContain('requires a final test');
});

it('allows publishing once the final test exists', function (): void {
    $this->course->forceFill(['requires_final_test' => true])->save();

    Assessment::factory()->create([
        'assessable_type' => Course::class,
        'assessable_id' => $this->course->getKey(),
        'type' => AssessmentType::Test,
    ]);

    expect(app(CoursePublishValidator::class)->blockers($this->course->refresh()))->toBe([]);
});

it('does not accept a lesson quiz in place of the course final test', function (): void {
    // ADR-002: a quiz hangs off a lesson or module, a test off the course.
    // They are the same table with a discriminator, which is exactly why this
    // needs asserting — the wrong one satisfying the gate would be silent.
    $this->course->forceFill(['requires_final_test' => true])->save();

    $lesson = Lesson::query()->firstOrFail();

    Assessment::factory()->create([
        'assessable_type' => Lesson::class,
        'assessable_id' => $lesson->getKey(),
        'type' => AssessmentType::Quiz,
    ]);

    $blockers = app(CoursePublishValidator::class)->blockers($this->course->refresh());

    expect(implode(' ', $blockers))->toContain('requires a final test');
});

it('names both ways out in the message', function (): void {
    // The author can add the test or drop the requirement; a blocker that
    // states only the problem sends them to ask somebody.
    $this->course->forceFill(['requires_final_test' => true])->save();

    $message = implode(' ', app(CoursePublishValidator::class)->blockers($this->course->refresh()));

    expect($message)->toContain('Add the final test')
        ->and($message)->toContain('turn the requirement off');
});
