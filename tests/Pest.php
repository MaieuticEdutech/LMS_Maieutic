<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case bindings
|--------------------------------------------------------------------------
|
| Feature tests get the full application container and a real PostgreSQL
| database (lms_test — see phpunit.xml). Feature tests are the PRIMARY layer
| for this project: most of what matters here is authorisation, enrollment
| and payment behaviour, none of which can be proven with mocks alone
| (planning.md §12.1).
|
| RefreshDatabase is applied to every Feature test so each runs inside a
| transaction that is rolled back afterwards — tests stay isolated and
| order-independent (planning.md §12.3).
|
| Unit tests deliberately get NO container and NO database. They cover pure
| logic only: Money arithmetic, grading rules, progress percentage maths,
| publish validators, signature verification. If a "unit" test needs the
| database, it is a feature test and belongs in tests/Feature.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Vite is disabled for the whole Feature suite in Tests\TestCase::setUp().
// See that class for why.

/*
|--------------------------------------------------------------------------
| Custom expectations
|--------------------------------------------------------------------------
|
| Domain-specific expectations are added here as the phases progress, e.g.
| ->toBeDeniedFor($user) for policy checks in Phase 2 onwards.
|
*/

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
|
| Shared test helpers live here. Phase 2 adds actingAsSuperAdmin(),
| actingAsInstructor() and actingAsStudent() once the role system exists.
|
*/
