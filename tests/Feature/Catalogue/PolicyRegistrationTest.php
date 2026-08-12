<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\MediaFile;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| Policy registration
|--------------------------------------------------------------------------
|
| Phase 3 DoD: "Every model has a factory and a REGISTERED policy."
|
| WHY THIS FILE EXISTS SEPARATELY FROM THE POLICY TESTS:
|
| CataloguePolicyTest calls the policy classes directly — it proves the RULES
| are right. It does not prove Laravel actually resolves them.
|
| Laravel auto-discovers policies by naming convention. Auto-discovery fails
| SILENTLY: rename a model, move a policy, and Gate::allows() starts returning
| false for everything with no error anywhere. On an authorisation layer, a
| silent failure that denies is survivable — but the same mechanism failing
| for a policy whose default is permissive would not be, and either way a
| broken gate should be loud.
|
| So this asserts the wiring, not the logic.
|
*/

it('resolves a policy for every domain model', function (string $model): void {
    expect(Gate::getPolicyFor($model))->not->toBeNull();
})->with([
    Course::class,
    Module::class,
    Lesson::class,
    MediaFile::class,
    User::class,
    AuditLog::class,
]);

it('routes authorisation through the registered policy, not a default', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->student()->create();
    $course = Course::factory()->create();

    // If the policy were not registered, both would return false and this
    // test would fail on the admin — which is exactly the signal we want.
    expect($admin->can('update', $course))->toBeTrue()
        ->and($student->can('update', $course))->toBeFalse();
});

it('routes lesson and media authorisation through their policies', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->student()->create();

    $course = Course::factory()->published()->create();
    $module = Module::factory()->forCourse($course)->published()->create();
    $lesson = Lesson::factory()->forModule($module)->published()->create();
    $file = MediaFile::factory()->attachedTo($lesson)->create();

    expect($admin->can('access', $lesson))->toBeTrue()
        ->and($student->can('access', $lesson))->toBeFalse()
        ->and($admin->can('access', $file))->toBeTrue()
        ->and($student->can('access', $file))->toBeFalse();
});

it('denies by default for an ability no policy defines', function (): void {
    // NFR-SEC-18: deny-by-default. An ability nobody wrote a rule for must be
    // refused, not silently allowed.
    expect(User::factory()->superAdmin()->create()->can('teleport', Course::factory()->create()))
        ->toBeFalse();
});
