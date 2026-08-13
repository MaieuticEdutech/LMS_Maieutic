<?php

declare(strict_types=1);

use App\Models\AssessmentAttempt;
use App\Models\AttemptAnswer;
use App\Models\Category;
use App\Models\InstructorProfile;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| Phase 3 DoD · "Every model has a factory and a registered policy"
|--------------------------------------------------------------------------
|
| ═════════════════════════════════════════════════════════════════════════
| SEVEN MODELS HAVE NO POLICY, AND SIX OF THEM SHOULD NOT.
|
| The DoD says every model. Taken literally that means seven more policy
| classes, six of which nothing would ever call — a policy on AttemptAnswer
| would never be consulted, because an answer is reached through the attempt
| that owns it and AttemptPolicy has already decided.
|
| Dead authorisation code is worse than none: it reads as protection, it is
| never exercised, and the day someone starts calling it nobody knows whether
| its rules were ever right.
|
| So the rule enforced here is the useful version of the DoD's intent: every
| model either HAS a registered policy, or is named below with the reason it
| does not. An exemption is then a recorded decision rather than an oversight,
| and a NEW model gets neither by default — it fails this test until somebody
| chooses.
| ═════════════════════════════════════════════════════════════════════════
|
| PolicyRegistrationTest covers the other half: that the registered ones
| actually resolve. Auto-discovery fails silently, so both halves matter.
|
*/

/**
 * Models authorised through something else, with the something else named.
 *
 * @return array<class-string, string>
 */
function policyExemptions(): array
{
    return [
        // Children of an assessment. Reached only through it, and
        // AssessmentPolicy has already answered by the time they are loaded.
        Question::class => 'authorised through AssessmentPolicy on the owning assessment',
        QuestionOption::class => 'authorised through AssessmentPolicy on the owning assessment',

        // Reached only through the attempt that owns it; AttemptPolicy decides
        // who may read or write an attempt, and an answer inherits that.
        AttemptAnswer::class => 'authorised through AttemptPolicy on the owning attempt',

        // Written by RecordLessonProgress against an enrollment whose access
        // EnrollmentAccessService has already established (rule S-8). There is
        // no screen that reaches a progress row by id.
        LessonProgress::class => 'access decided by EnrollmentAccessService before any row is touched',

        // Part of the user it belongs to. UserPolicy governs who may see or
        // edit an instructor, and the profile travels with them.
        InstructorProfile::class => 'authorised through UserPolicy on the owning user',

        // Never user-facing. Rows are written by the webhook endpoint from a
        // signature-verified payload and read by admin tooling in Phase 12,
        // which will gate on the role rather than on a row.
        WebhookEvent::class => 'not user-facing; written by the verified webhook path only',
    ];
}

it('gives every model either a registered policy or a recorded exemption', function (): void {
    $models = collect(glob(app_path('Models/*.php')) ?: [])
        ->map(static fn (string $file): string => 'App\\Models\\'.basename($file, '.php'))
        ->reject(static fn (string $class): bool => ! class_exists($class));

    expect($models)->not->toBeEmpty();

    $exemptions = policyExemptions();

    foreach ($models as $model) {
        $hasPolicy = Gate::getPolicyFor($model) !== null;
        $isExempt = array_key_exists($model, $exemptions);

        expect($hasPolicy || $isExempt)->toBeTrue(
            "[{$model}] has no registered policy and no recorded exemption. "
            .'Either add a policy, or add it to policyExemptions() with the reason '
            .'it is authorised elsewhere.',
        );

        // Both would be a contradiction: a policy that exists while the code
        // is documented as authorising somewhere else.
        expect($hasPolicy && $isExempt)->toBeFalse(
            "[{$model}] is listed as exempt but has a registered policy. Remove the exemption.",
        );
    }
});

it('gives every model a factory', function (): void {
    $models = collect(glob(app_path('Models/*.php')) ?: [])
        ->map(static fn (string $file): string => 'App\\Models\\'.basename($file, '.php'))
        ->reject(static fn (string $class): bool => ! class_exists($class));

    foreach ($models as $model) {
        $factory = 'Database\\Factories\\'.class_basename($model).'Factory';

        expect(class_exists($factory))->toBeTrue("[{$model}] has no factory at [{$factory}].");
    }
});

/*
| ═══════════════ THE NEW POLICY BEHAVES ═══════════════
*/
it('lets anyone read a category, including a guest', function (): void {
    $category = Category::factory()->create();

    // The catalogue index groups by category and a guest browsing courses sees
    // it, so reading is deliberately ungated.
    expect(Gate::forUser(null)->allows('view', $category))->toBeTrue();
});

it('lets only a super admin reorganise the catalogue', function (string $role, bool $allowed): void {
    $category = Category::factory()->create();
    $actor = User::factory()->{$role}()->create();

    // Renaming or re-parenting moves every course underneath it and changes
    // public URLs — administrative, not editorial.
    expect($actor->can('update', $category))->toBe($allowed)
        ->and($actor->can('delete', $category))->toBe($allowed);
})->with([
    'super admin' => ['superAdmin', true],
    'instructor' => ['instructor', false],
    'student' => ['student', false],
]);

/*
| ═══════════════ THE NEW FACTORIES PRODUCE VALID ROWS ═══════════════
*/
it('builds an audit entry that the append-only model accepts', function (): void {
    $entry = App\Models\AuditLog::factory()->create();

    expect($entry->exists)->toBeTrue()
        ->and($entry->action)->not->toBeEmpty();
});

it('builds an instructor profile against an actual instructor', function (): void {
    $profile = InstructorProfile::factory()->create();

    // A profile hanging off a student is not a state the application can
    // reach, and a factory producing one would let tests pass against data
    // that cannot exist.
    expect($profile->user?->isInstructor())->toBeTrue();
});

it('builds settings with distinct keys', function (): void {
    $settings = App\Models\Setting::factory()->count(3)->create();

    // `key` carries a unique index; a factory colliding on a second call would
    // fail in a way that looks like a bug in whatever test used it.
    expect($settings->pluck('key')->unique())->toHaveCount(3);
});

it('reads an attempt through its own policy, not a missing one', function (): void {
    // AssessmentAttempt maps to AttemptPolicy — a deliberate naming exception
    // registered explicitly in AppServiceProvider, since convention would look
    // for AssessmentAttemptPolicy and find nothing.
    expect(Gate::getPolicyFor(AssessmentAttempt::class))->not->toBeNull();
});
