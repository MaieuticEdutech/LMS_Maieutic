<?php

declare(strict_types=1);

use App\Actions\Catalog\ReorderLessons;
use App\Actions\Catalog\ReorderModules;
use App\Exceptions\ReorderException;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Phase 5 · Drag-and-drop reordering (FR-CNT-03, FR-CNT-04)
|--------------------------------------------------------------------------
|
| Two properties, both load-bearing:
|   1. the submitted ID set must match the current children EXACTLY
|   2. the update is atomic — all positions change or none do
|
| A partial reorder leaves duplicate positions, and duplicate positions make
| curriculum order non-deterministic: the bug that presents as "the lessons
| keep shuffling" and is miserable to track down.
|
*/

/**
 * Ordered ids as a genuine list<int>, matching what the Actions expect.
 *
 * Takes the plucked Collection rather than the query builder: array_values()
 * is what proves list-ness, and this avoids threading Eloquent's generics
 * through a test helper.
 *
 * @param  Illuminate\Support\Collection<int, mixed>  $ids
 * @return list<int>
 */
function toIntList(Illuminate\Support\Collection $ids): array
{
    return array_values($ids->map(static fn (mixed $id): int => (int) $id)->all());
}

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->course = Course::factory()->create();

    /** @var list<Module> $modules */
    $modules = [];
    foreach (range(0, 2) as $i) {
        $modules[] = Module::factory()->forCourse($this->course)->atPosition($i)->create();
    }

    $this->moduleA = $modules[0];
    $this->moduleB = $modules[1];
    $this->moduleC = $modules[2];
    $this->moduleIds = array_map(static fn (Module $m): int => (int) $m->id, $modules);
});

it('reorders modules', function (): void {
    $reversed = array_values(array_reverse($this->moduleIds));

    app(ReorderModules::class)->handle($this->course, $reversed, $this->admin);

    expect(toIntList($this->course->modules()->orderBy('position')->pluck('id')))->toBe($reversed);
});

it('leaves no duplicate positions after a reorder', function (): void {
    $shuffled = [$this->moduleIds[2], $this->moduleIds[0], $this->moduleIds[1]];

    app(ReorderModules::class)->handle($this->course, $shuffled, $this->admin);

    $positions = $this->course->modules()->pluck('position')->all();

    expect($positions)->toHaveCount(count(array_unique($positions)));
});

/*
| EXACT SET MATCH — the check that stops corruption.
*/
it('rejects a partial set and changes nothing', function (): void {
    $before = toIntList($this->course->modules()->orderBy('position')->pluck('id'));
    $partial = [$this->moduleIds[0], $this->moduleIds[1]]; // one missing

    expect(fn () => app(ReorderModules::class)->handle($this->course, $partial, $this->admin))
        ->toThrow(ReorderException::class);

    expect(toIntList($this->course->modules()->orderBy('position')->pluck('id')))->toBe($before);
});

it('rejects a set containing a module from another course', function (): void {
    // The dangerous case: a superset would silently ADOPT another course's
    // module — a data-integrity hole reachable from a form post.
    $foreign = Module::factory()->forCourse(Course::factory()->create())->create();

    $ids = array_values([...$this->moduleIds, (int) $foreign->id]);

    expect(fn () => app(ReorderModules::class)->handle($this->course, $ids, $this->admin))
        ->toThrow(ReorderException::class);

    expect($foreign->refresh()->course_id)->not->toBe($this->course->id);
});

it('rejects duplicate ids in the submitted order', function (): void {
    $ids = [$this->moduleIds[0], $this->moduleIds[0], $this->moduleIds[1]];

    expect(fn () => app(ReorderModules::class)->handle($this->course, $ids, $this->admin))
        ->toThrow(ReorderException::class);
});

it('rejects an empty order when modules exist', function (): void {
    expect(fn () => app(ReorderModules::class)->handle($this->course, [], $this->admin))
        ->toThrow(ReorderException::class);
});

/*
| Lessons — same rules, and one extra risk: a superset could pull a lesson
| out of a DIFFERENT module of the same course, silently re-parenting it.
*/
it('reorders lessons within a module', function (): void {
    /** @var Module $module */
    $module = $this->moduleA;

    $lessonIds = [];
    foreach (range(0, 2) as $i) {
        $lessonIds[] = (int) Lesson::factory()->forModule($module)->atPosition($i)->create()->id;
    }

    $reversed = array_reverse($lessonIds);

    app(ReorderLessons::class)->handle($module, $reversed, $this->admin);

    expect(toIntList($module->lessons()->orderBy('position')->pluck('id')))->toBe($reversed);
});

it('refuses to re-parent a lesson from another module of the same course', function (): void {
    /** @var Module $moduleA */
    $moduleA = $this->moduleA;
    /** @var Module $moduleB */
    $moduleB = $this->moduleB;

    $lessonA = Lesson::factory()->forModule($moduleA)->create();
    $lessonB = Lesson::factory()->forModule($moduleB)->create();

    expect(fn () => app(ReorderLessons::class)->handle(
        $moduleA,
        [(int) $lessonA->id, (int) $lessonB->id],
        $this->admin,
    ))->toThrow(ReorderException::class);

    // Still where it started.
    expect($lessonB->refresh()->module_id)->toBe($moduleB->id);
});

it('audits a successful reorder', function (): void {
    app(ReorderModules::class)->handle($this->course, array_values(array_reverse($this->moduleIds)), $this->admin);

    expect(App\Models\AuditLog::query()->where('action', 'course.modules.reordered')->exists())->toBeTrue();
});
