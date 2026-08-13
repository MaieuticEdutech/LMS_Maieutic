<?php

declare(strict_types=1);

use App\Enums\LessonType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Services\Content\CoursePublishValidator;

/*
|--------------------------------------------------------------------------
| Phase 5 · Publish validation (FR-CRS-04, AC-17)
|--------------------------------------------------------------------------
|
| ONE implementation, two consumers: the Course Builder renders these as a
| live checklist, and PublishCourse enforces them. If the UI kept its own copy
| of the rules they would drift, and the drift would surface as a checklist
| saying "ready" beside a button that refuses.
|
*/

beforeEach(function (): void {
    $this->validator = app(CoursePublishValidator::class);
});

/**
 * @param  array<string, mixed>  $courseAttrs
 */
function courseWithContent(array $courseAttrs = []): Course
{
    $course = Course::factory()->create(array_merge([
        'description' => 'A description.',
        'thumbnail_path' => 'thumbnails/x.jpg',
    ], $courseAttrs));

    $module = Module::factory()->forCourse($course)->published()->create();
    Lesson::factory()->forModule($module)->published()->create([
        'type' => LessonType::Text,
        'body' => '<p>Content.</p>',
    ]);

    return $course->refresh();
}

it('passes a complete course', function (): void {
    expect($this->validator->blockers(courseWithContent()))->toBe([])
        ->and($this->validator->passes(courseWithContent()))->toBeTrue();
});

it('blocks a course with no description', function (): void {
    $course = courseWithContent(['description' => null]);

    expect(implode(' ', $this->validator->blockers($course)))->toContain('description');
});

it('blocks a course with no thumbnail', function (): void {
    $course = courseWithContent(['thumbnail_path' => null]);

    expect(implode(' ', $this->validator->blockers($course)))->toContain('thumbnail');
});

it('blocks a course with no published module', function (): void {
    $course = Course::factory()->create([
        'description' => 'x',
        'thumbnail_path' => 'y.jpg',
    ]);
    // Present but unpublished — invisible to students, so it does not count.
    Module::factory()->forCourse($course)->create();

    expect(implode(' ', $this->validator->blockers($course)))->toContain('published module');
});

it('blocks a module that has no published lessons', function (): void {
    $course = Course::factory()->create(['description' => 'x', 'thumbnail_path' => 'y.jpg']);
    $module = Module::factory()->forCourse($course)->published()->create(['title' => 'Empty Module']);
    Lesson::factory()->forModule($module)->create(); // unpublished

    expect(implode(' ', $this->validator->blockers($course->refresh())))
        ->toContain('Empty Module')
        ->toContain('no published lessons');
});

/*
| PER-TYPE RULES COME FROM THE HANDLER, NOT FROM THE VALIDATOR (FR-CNT-07).
|
| This is what lets a new content type bring its own publish rules without
| anyone editing CoursePublishValidator.
*/
it('blocks a video lesson with no video attached', function (): void {
    $course = Course::factory()->create(['description' => 'x', 'thumbnail_path' => 'y.jpg']);
    $module = Module::factory()->forCourse($course)->published()->create();
    Lesson::factory()->forModule($module)->published()->create([
        'title' => 'Intro Video',
        'type' => LessonType::Video,
        'duration_seconds' => 300,
    ]);

    expect(implode(' ', $this->validator->blockers($course->refresh())))
        ->toContain('Intro Video')
        ->toContain('no file attached');
});

it('blocks a video lesson with no duration', function (): void {
    $course = Course::factory()->create(['description' => 'x', 'thumbnail_path' => 'y.jpg']);
    $module = Module::factory()->forCourse($course)->published()->create();
    $lesson = Lesson::factory()->forModule($module)->published()->create([
        'title' => 'No Duration',
        'type' => LessonType::Video,
        'duration_seconds' => null,
    ]);
    App\Models\MediaFile::factory()->video()->attachedTo($lesson)->create();

    expect(implode(' ', $this->validator->blockers($course->refresh())))->toContain('no duration');
});

it('blocks a text lesson with an empty body', function (): void {
    $course = Course::factory()->create(['description' => 'x', 'thumbnail_path' => 'y.jpg']);
    $module = Module::factory()->forCourse($course)->published()->create();
    Lesson::factory()->forModule($module)->published()->create([
        'title' => 'Empty Text',
        'type' => LessonType::Text,
        'body' => null,
    ]);

    expect(implode(' ', $this->validator->blockers($course->refresh())))
        ->toContain('Empty Text')
        ->toContain('no content');
});

/*
| A quiz lesson with no assessment attached (or an unpublished one) can
| never publish — Phase 8's QuizContentHandler. Fail-safe: blocking is
| visible; allowing would put a dead lesson in front of a paying student.
*/
it('blocks a quiz lesson with no assessment attached', function (): void {
    $course = Course::factory()->create(['description' => 'x', 'thumbnail_path' => 'y.jpg']);
    $module = Module::factory()->forCourse($course)->published()->create();
    Lesson::factory()->forModule($module)->published()->create([
        'title' => 'Chapter Quiz',
        'type' => LessonType::Quiz,
    ]);

    expect(implode(' ', $this->validator->blockers($course->refresh())))
        ->toContain('Chapter Quiz')
        ->toContain('no assessment attached');
});

it('allows a quiz lesson once its assessment is published', function (): void {
    $course = Course::factory()->create(['description' => 'x', 'thumbnail_path' => 'y.jpg']);
    $module = Module::factory()->forCourse($course)->published()->create();
    $lesson = Lesson::factory()->forModule($module)->published()->create([
        'title' => 'Chapter Quiz',
        'type' => LessonType::Quiz,
    ]);

    \App\Models\Assessment::factory()->create([
        'assessable_type' => Lesson::class,
        'assessable_id' => $lesson->id,
        'is_published' => true,
    ]);

    expect($this->validator->blockers($course->refresh()))->not->toContain(
        'Lesson "Chapter Quiz" is a quiz with no assessment attached yet.',
    );
});

it('reports every blocker at once, not just the first', function (): void {
    $course = Course::factory()->create(['description' => null, 'thumbnail_path' => null]);

    // No description, no thumbnail, no published module = three reasons.
    expect($this->validator->blockers($course))->toHaveCount(3);
});
