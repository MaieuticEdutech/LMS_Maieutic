<?php

declare(strict_types=1);

use App\Enums\LessonType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;

it('shows metadata for a published course to a guest', function (): void {
    $course = Course::factory()->published()->create([
        'title' => 'System Design Fundamentals',
        'outcomes' => ['Design for scale'],
    ]);

    $this->get(route('catalogue.show', $course))
        ->assertOk()
        ->assertSee('System Design Fundamentals')
        ->assertSee('Design for scale');
});

it('refuses a guest opening a draft course by direct url', function (): void {
    $course = Course::factory()->create(); // draft by default

    $this->get(route('catalogue.show', $course))->assertForbidden();
});

it('refuses a guest opening an archived course by direct url', function (): void {
    $course = Course::factory()->archived()->create();

    $this->get(route('catalogue.show', $course))->assertForbidden();
});

it('shows only published lessons in published modules — never a draft one', function (): void {
    $course = Course::factory()->published()->create();
    $publishedModule = Module::factory()->forCourse($course)->published()->create(['title' => 'Visible module']);
    $draftModule = Module::factory()->forCourse($course)->create(['title' => 'Hidden module']);

    Lesson::factory()->forModule($publishedModule)->published()->create(['title' => 'Visible lesson']);
    Lesson::factory()->forModule($publishedModule)->create(['title' => 'Hidden draft lesson']);
    Lesson::factory()->forModule($draftModule)->published()->create(['title' => 'Lesson in hidden module']);

    $this->get(route('catalogue.show', $course))
        ->assertSee('Visible lesson')
        ->assertDontSee('Hidden draft lesson')
        ->assertDontSee('Hidden module')
        ->assertDontSee('Lesson in hidden module');
});

it('never leaks a lesson body, a media route or a signed url fragment (AC-01)', function (): void {
    $course = Course::factory()->published()->create();
    $module = Module::factory()->forCourse($course)->published()->create();

    Lesson::factory()
        ->forModule($module)
        ->ofType(LessonType::Text)
        ->published()
        ->create(['body' => '<p>SECRET LESSON BODY THAT MUST NEVER LEAK</p>']);

    $response = $this->get(route('catalogue.show', $course));

    $response->assertOk()
        ->assertDontSee('SECRET LESSON BODY THAT MUST NEVER LEAK', false)
        ->assertDontSee('/media/', false);
});
