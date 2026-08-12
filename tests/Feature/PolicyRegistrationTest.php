<?php

declare(strict_types=1);

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| Policy registration — Tracks B and C
|--------------------------------------------------------------------------
|
| Same discipline as tests/Feature/Catalogue/PolicyRegistrationTest.php
| (Track A): proves Laravel actually RESOLVES a policy for each model, not
| just that the policy class's rules are correct in isolation.
|
| WRITTEN AFTER DISCOVERING THE FAILURE MODE FIRSTHAND, not proactively:
| `AssessmentAttempt` → `AttemptPolicy` breaks Laravel's naming-convention
| auto-discovery (which looks for `AssessmentAttemptPolicy`). Every
| AttemptPolicy test failed silently — `can()` returned `false` for
| everything, no error anywhere — until `Gate::policy()` was registered
| explicitly in AppServiceProvider::configureAuthorization(). This file
| exists so that regression is impossible to reintroduce quietly.
|
*/

it('resolves a policy for every domain model this session built', function (string $model): void {
    expect(Gate::getPolicyFor($model))->not->toBeNull();
})->with([
    Assessment::class,
    AssessmentAttempt::class,
    Order::class,
    Payment::class,
    Enrollment::class,
]);

it('routes AssessmentAttempt authorisation through AttemptPolicy specifically, not a default', function (): void {
    $owner = User::factory()->student()->create();
    $other = User::factory()->student()->create();
    $attempt = AssessmentAttempt::factory()->create(['user_id' => $owner->id]);

    // If AttemptPolicy were not registered (the exact bug this test guards
    // against), both would return false and this test would fail on the
    // owner — which is the signal that matters.
    expect($owner->can('answer', $attempt))->toBeTrue()
        ->and($other->can('answer', $attempt))->toBeFalse();
});
