<?php

declare(strict_types=1);

use App\Actions\Catalog\CreateLesson;
use App\Actions\Catalog\CreateModule;
use App\Actions\Catalog\DeleteLesson;
use App\Actions\Catalog\DeleteModule;
use App\Actions\Catalog\UpdateLesson;
use App\Enums\LessonType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Services\Content\CourseCounterService;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Phase 5 · Counter caches (principle P-8)
|--------------------------------------------------------------------------
|
| These counters exist so the catalogue can render "12 lessons · 3h 40m"
| without counting rows for every card on the page.
|
| They are a CACHE, never truth. The final test in this file is the one that
| keeps that claim honest: deliberately corrupt every counter, run the rebuild,
| and the correct values come back from the rows alone.
|
*/

beforeEach(function (): void {
    Queue::fake();
    $this->admin = User::factory()->superAdmin()->create();
    $this->course = Course::factory()->create();
});

it('counts only PUBLISHED content', function (): void {
    $published = Module::factory()->forCourse($this->course)->published()->create();
    Module::factory()->forCourse($this->course)->create(); // draft

    Lesson::factory()->forModule($published)->published()->create(['duration_seconds' => 100]);
    Lesson::factory()->forModule($published)->create(['duration_seconds' => 999]); // draft

    app(CourseCounterService::class)->refreshCourse($this->course);
    $this->course->refresh();

    // Counting drafts would advertise lessons that do not exist yet to a
    // prospective buyer.
    expect($this->course->modules_count)->toBe(1)
        ->and($this->course->lessons_count)->toBe(1)
        ->and($this->course->total_duration_seconds)->toBe(100);
});

it('updates counters when a module is added', function (): void {
    app(CreateModule::class)->handle($this->course, ['title' => 'M1', 'is_published' => true], $this->admin);

    expect($this->course->refresh()->modules_count)->toBe(1);
});

it('updates counters when a lesson is published', function (): void {
    $module = Module::factory()->forCourse($this->course)->published()->create();

    $lesson = app(CreateLesson::class)->handle($module, [
        'title' => 'L1',
        'type' => LessonType::Text,
        'body' => '<p>x</p>',
        'duration_seconds' => 300,
    ], $this->admin);

    // Created as a draft, so it does not count yet.
    expect($this->course->refresh()->lessons_count)->toBe(0);

    app(UpdateLesson::class)->handle($lesson, ['is_published' => true], $this->admin);

    expect($this->course->refresh()->lessons_count)->toBe(1)
        ->and($this->course->total_duration_seconds)->toBe(300)
        ->and($module->refresh()->lessons_count)->toBe(1);
});

it('updates counters when a lesson is deleted', function (): void {
    $module = Module::factory()->forCourse($this->course)->published()->create();
    $lesson = Lesson::factory()->forModule($module)->published()->create(['duration_seconds' => 60]);

    app(CourseCounterService::class)->refreshModule($module);
    expect($this->course->refresh()->lessons_count)->toBe(1);

    app(DeleteLesson::class)->handle($lesson, $this->admin);

    expect($this->course->refresh()->lessons_count)->toBe(0)
        ->and($this->course->total_duration_seconds)->toBe(0);
});

it('updates counters when a module is deleted', function (): void {
    $module = Module::factory()->forCourse($this->course)->published()->create();
    Lesson::factory()->forModule($module)->published()->create();

    app(CourseCounterService::class)->refreshModule($module);
    expect($this->course->refresh()->modules_count)->toBe(1);

    app(DeleteModule::class)->handle($module, $this->admin);

    expect($this->course->refresh()->modules_count)->toBe(0)
        ->and($this->course->lessons_count)->toBe(0);
});

it('sums duration across several modules', function (): void {
    foreach ([[100, 200], [300]] as $durations) {
        $module = Module::factory()->forCourse($this->course)->published()->create();

        foreach ($durations as $d) {
            Lesson::factory()->forModule($module)->published()->create(['duration_seconds' => $d]);
        }
    }

    app(CourseCounterService::class)->refreshCourse($this->course);

    expect($this->course->refresh()->total_duration_seconds)->toBe(600)
        ->and($this->course->lessons_count)->toBe(3)
        ->and($this->course->modules_count)->toBe(2);
});

it('ignores soft-deleted lessons', function (): void {
    $module = Module::factory()->forCourse($this->course)->published()->create();
    $keep = Lesson::factory()->forModule($module)->published()->create(['duration_seconds' => 50]);
    $gone = Lesson::factory()->forModule($module)->published()->create(['duration_seconds' => 50]);

    $gone->delete();

    app(CourseCounterService::class)->refreshCourse($this->course);

    expect($this->course->refresh()->lessons_count)->toBe(1)
        ->and($this->course->total_duration_seconds)->toBe(50)
        ->and($keep->exists)->toBeTrue();
});

/*
| ══════════════════════════════════════════════════════════════════════════
| THE TEST THAT MAKES CACHING SAFE.
|
| If a counter ever drifts, one command fixes it — because the truth is the
| rows, and the counters are only ever a faster way of reading them. Were
| this not reproducible, they would be data rather than cache, and a drift
| would be unrecoverable.
| ══════════════════════════════════════════════════════════════════════════
*/
it('rebuilds every counter from the rows alone', function (): void {
    $module = Module::factory()->forCourse($this->course)->published()->create();
    Lesson::factory()->forModule($module)->published()->count(3)->create(['duration_seconds' => 100]);

    // Corrupt every cached value.
    $this->course->forceFill([
        'modules_count' => 999,
        'lessons_count' => 999,
        'total_duration_seconds' => 999999,
    ])->save();
    $module->forceFill(['lessons_count' => 999])->save();

    app(CourseCounterService::class)->rebuildAll();

    expect($this->course->refresh()->modules_count)->toBe(1)
        ->and($this->course->lessons_count)->toBe(3)
        ->and($this->course->total_duration_seconds)->toBe(300)
        ->and($module->refresh()->lessons_count)->toBe(3);
});

it('exposes the rebuild as an artisan command', function (): void {
    expect(Illuminate\Support\Facades\Artisan::call('lms:counters:rebuild'))->toBe(0);
});
