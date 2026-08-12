<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Settings\BrandingService;
use App\Services\Settings\SettingsRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

/*
|--------------------------------------------------------------------------
| Phase 2 schema, model and service invariants
|--------------------------------------------------------------------------
|
| Guards the database-level protections that the application relies on but
| never re-checks at runtime (architecture.md §6.5, ADR-012).
|
*/

it('creates the phase 2 tables', function (string $table): void {
    expect(Schema::hasTable($table))->toBeTrue();
})->with(['users', 'instructor_profiles', 'settings', 'audit_logs']);

it('creates every Track B and Track C table (Phase 3, complete)', function (string $table): void {
    // Formerly a negative guard ("Track A must not build Track B's or
    // Track C's slice", planning.md §21.5) — that mattered while these
    // tables didn't exist yet. Now that both tracks are complete (Srivathsa
    // owns both — see PROGRESS.md), there is nothing left to assert absent,
    // so this asserts the positive instead: every table both tracks were
    // responsible for actually exists. See the individual *SchemaTest files
    // for the per-table invariants (CHECK constraints, unique constraints,
    // cascade behaviour) this test does not duplicate.
    expect(Schema::hasTable($table))->toBeTrue();
})->with([
    // Track B — Srivathsa
    'assessments', 'questions', 'question_options', 'assessment_attempts', 'attempt_answers',
    // Track C — Srivathsa (formerly Shashank's, see PROGRESS.md)
    'webhook_events', 'email_logs', 'orders', 'payments', 'enrollments', 'lesson_progress',
]);

/*
| CHECK CONSTRAINTS (ADR-012). The database refuses an illegal role or status
| even if application code is bypassed by a console command or a raw query.
*/
it('rejects an invalid role at the database level', function (): void {
    $user = User::factory()->create();

    expect(fn () => DB::table('users')->where('id', $user->id)->update(['role' => 'wizard']))
        ->toThrow(QueryException::class);
});

it('rejects an invalid status at the database level', function (): void {
    $user = User::factory()->create();

    expect(fn () => DB::table('users')->where('id', $user->id)->update(['status' => 'vibing']))
        ->toThrow(QueryException::class);
});

it('accepts every role and status combination the application can produce', function (): void {
    // The CHECK constraints must reject illegal values WITHOUT rejecting legal
    // ones. A constraint that is too tight is as much a defect as one that is
    // too loose — it would break a legitimate state transition in production.
    foreach (UserRole::cases() as $role) {
        foreach (UserStatus::cases() as $status) {
            $user = User::factory()->create();
            $user->forceFill(['role' => $role, 'status' => $status])->save();
            $user->refresh();

            expect($user->role)->toBe($role)
                ->and($user->status)->toBe($status);
        }
    }
});

it('enforces a unique email', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    expect(fn () => User::factory()->create(['email' => 'taken@example.com']))
        ->toThrow(QueryException::class);
});

/*
| MASS ASSIGNMENT (NFR-SEC-07).
|
| `role` and `status` must never be fillable. With
| preventSilentlyDiscardingAttributes on, an attempt throws rather than
| silently no-opping — so the mistake surfaces at development time.
*/
it('refuses to mass-assign role or status', function (array $payload): void {
    expect(fn () => User::factory()->create()->fill($payload))
        ->toThrow(Exception::class);
})->with([
    'role' => [['role' => UserRole::SuperAdmin]],
    'status' => [['status' => UserStatus::Active]],
]);

it('normalises email on every write path, not just registration', function (): void {
    $user = User::factory()->create(['email' => '  MiXeD@Example.COM ']);

    expect($user->refresh()->email)->toBe('mixed@example.com');
});

it('soft deletes users so financial history survives', function (): void {
    $user = User::factory()->create();
    $user->delete();

    expect(User::query()->find($user->id))->toBeNull()
        ->and(User::withTrashed()->find($user->id))->not->toBeNull();
});

/*
| APPEND-ONLY AUDIT LOG (NFR-SEC-17).
|
| Enforced at the ORM level so the guarantee holds even for code that never
| consults a policy.
*/
it('refuses to update an audit entry', function (): void {
    $entry = AuditLog::query()->create(['action' => 'test.event', 'description' => 'Original.']);

    expect(fn () => $entry->update(['description' => 'Rewritten.']))
        ->toThrow(LogicException::class);
});

it('refuses to delete an audit entry', function (): void {
    $entry = AuditLog::query()->create(['action' => 'test.event']);

    expect(fn () => $entry->delete())->toThrow(LogicException::class);
});

it('keeps audit entries when their actor is deleted', function (): void {
    $user = User::factory()->create();
    $entry = AuditLog::query()->create(['action' => 'test.event', 'user_id' => $user->id]);

    $user->forceDelete();

    // SET NULL, not CASCADE: deleting a user must never erase the record of
    // what they did.
    $survivor = AuditLog::query()->find($entry->id);

    expect($survivor)->not->toBeNull()
        ->and($survivor?->user_id)->toBeNull()
        ->and($survivor?->action)->toBe('test.event');
});

it('redacts secrets from audit changes', function (): void {
    $logger = app(App\Services\Audit\AuditLogger::class);

    $entry = $logger->record(
        action: 'test.event',
        changes: ['before' => ['password' => 'hunter2'], 'after' => ['password' => 'hunter3']],
    );

    expect($entry->changes)->toBe([
        'before' => ['password' => '[redacted]'],
        'after' => ['password' => '[redacted]'],
    ]);
});

/*
| MULTI-TENANCY SEAMS (rules S-1, FR-SYS-01, FR-SYS-06).
|
| These prove the seam is real, not aspirational: changing a database setting
| changes what the application calls itself, with no code change.
*/
it('resolves organisation identity from settings, not from code', function (): void {
    $settings = app(SettingsRepository::class);
    $branding = app(BrandingService::class);

    $settings->set('branding.organisation_name', 'Acme Institute', 'branding');

    expect($branding->organisationName())->toBe('Acme Institute');
});

it('falls back gracefully when a branding setting is absent', function (): void {
    app(SettingsRepository::class)->flush();

    expect(app(BrandingService::class)->organisationName())->not->toBe('');
});

it('shows the organisation name from settings on the login page', function (): void {
    app(SettingsRepository::class)->set('branding.organisation_name', 'Acme Institute', 'branding');

    $this->get('/login')->assertOk()->assertSee('Acme Institute');
});
