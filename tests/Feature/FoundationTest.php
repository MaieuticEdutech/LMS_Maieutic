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
    // This guard moves forward with each phase rather than being deleted, so
    // "we did not build ahead" stays continuously asserted rather than being
    // checked once and forgotten.
    //
    //   Phase 1 → asserted `users` absent      (a Phase 2 table)
    //   Phase 2 → created it; guard moved to the Phase 3 boundary
    //   Phase 3 → COMPLETE, all three tracks. Track B and Track C are both
    //             now owned by Srivathsa (confirmed 2026-08-12, not yet
    //             reflected in planning.md/TRACK-C-SHASHANK.md — see
    //             PROGRESS.md), which is why one person's branch legitimately
    //             built every table below. Nothing is left in the "not yet
    //             built" half of this guard because Phase 4 (Admin Shell)
    //             introduces no new tables — the next entry here belongs to
    //             whichever future phase first adds one.
    expect(Schema::hasTable('users'))->toBeTrue();

    // Track A (Govind).
    foreach (['categories', 'courses', 'course_instructor', 'modules', 'lessons', 'media_files'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    // Track B (Srivathsa) — all five.
    foreach (['assessments', 'questions', 'question_options', 'assessment_attempts', 'attempt_answers'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    // Track C (Srivathsa, formerly Shashank's — see PROGRESS.md) — all six.
    foreach (['webhook_events', 'email_logs', 'orders', 'payments', 'enrollments', 'lesson_progress'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
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
