<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Phase 1 — Foundation smoke tests
|--------------------------------------------------------------------------
|
| These prove the skeleton is genuinely wired, not merely present:
|   - the application boots and serves a page
|   - the test suite reaches a real PostgreSQL database and migrates it
|   - the health endpoint reports real dependency state
|   - protected storage is configured privately
|
| Phase 1's Testing Requirements (phases.md).
|
*/

it('serves the home page', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee(config()->string('app.name'));
});

it('connects to a real postgresql database and has migrated', function (): void {
    // Guards against the suite silently falling back to SQLite. From Phase 3
    // the schema depends on JSONB, partial unique indexes and CHECK
    // constraints, none of which SQLite implements — so a green suite on
    // SQLite would be worthless.
    expect(DB::connection()->getDriverName())->toBe('pgsql');

    expect(DB::connection()->getDatabaseName())->toBe('lms_test');

    expect(Schema::hasTable('migrations'))->toBeTrue();
    expect(Schema::hasTable('sessions'))->toBeTrue();
    expect(Schema::hasTable('password_reset_tokens'))->toBeTrue();
});

it('has not built ahead of the current phase', function (): void {
    // Phase 1 asserted `users` did NOT exist, because it is a Phase 2 domain
    // table. Phase 2 has since created it with the LMS schema (role, status,
    // nullable password), so that assertion is now inverted — deliberately,
    // and recorded in the Phase 2 report rather than quietly deleted.
    //
    // The guard itself still matters: it now protects the Phase 3 boundary.
    //
    // `assessments` is deliberately absent from the "not yet built" list
    // below: Track B created it in Phase 3 (see AssessmentSchemaTest), the
    // same kind of inversion recorded for `users` above. The other Phase 3
    // domain tables (Tracks A and C) are not this track's to create and stay
    // guarded here until their own tracks land them.
    expect(Schema::hasTable('users'))->toBeTrue();
    expect(Schema::hasTable('assessments'))->toBeTrue();

    foreach (['courses', 'modules', 'lessons', 'enrollments', 'orders', 'payments'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
});

it('reports healthy when every dependency is reachable', function (): void {
    Storage::fake(config()->string('lms.disks.content'));

    $this->getJson('/up')
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'checks' => [
                'database' => 'ok',
                'cache' => 'ok',
                'storage' => 'ok',
            ],
        ]);
});

it('never exposes the protected content disk publicly', function (): void {
    // FR-FILE-03 / NFR-SEC-12: the content disk must be private and must not
    // be servable by Laravel's built-in local-disk route, which would bypass
    // MediaFilePolicy entirely once Phase 6 lands.
    $disk = config()->string('lms.disks.content');

    expect(config("filesystems.disks.{$disk}.visibility"))->toBe('private');
    expect(config("filesystems.disks.{$disk}.serve"))->toBeFalse();
});
