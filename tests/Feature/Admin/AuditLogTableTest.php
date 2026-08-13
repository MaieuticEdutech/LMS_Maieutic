<?php

declare(strict_types=1);

use App\Livewire\Admin\AuditLogTable;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| AuditLogTable — Phase 4 Checkpoint 6
|--------------------------------------------------------------------------
|
| AuditLog has no factory (it is written only by App\Services\Audit\
| AuditLogger, never by a test-facing factory) and its Eloquent model throws
| on update(), so rows with a specific `created_at` cannot be built via
| ->create() followed by a timestamp tweak. Inserting straight through the
| query builder sidesteps both the mass-assignment guard on `created_at`
| (not in $fillable) and the model's append-only guard (a raw insert never
| fires Eloquent's updating/deleting events) while still exercising the real
| table and its jsonb `changes` column.
|
*/

/**
 * @param  array<string, mixed>  $overrides
 */
function createAuditLog(array $overrides = []): AuditLog
{
    $id = DB::table('audit_logs')->insertGetId(array_merge([
        'user_id' => null,
        'action' => 'test.action.performed',
        'auditable_type' => null,
        'auditable_id' => null,
        'description' => 'A test event occurred.',
        'changes' => null,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PestTestAgent',
        'created_at' => now(),
    ], $overrides));

    return AuditLog::query()->findOrFail($id);
}

it('lists entries newest first', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $older = createAuditLog(['action' => 'older.event', 'created_at' => now()->subDay()]);
    $newer = createAuditLog(['action' => 'newer.event', 'created_at' => now()]);

    // Direct instantiation, not Livewire::test()->instance() — Larastan can't
    // resolve Testable's TComponent generic through ->instance() (the same
    // gap already worked around in WithAdminTableTest.php, Checkpoint 1), so
    // calling a component-specific method on it fails static analysis even
    // though it works at runtime. Constructing directly and calling mount()
    // sidesteps the generic entirely for tests that only need the component's
    // own data methods, not a real render cycle.
    $component = new AuditLogTable;
    $component->mount();

    $rows = $component->rows();

    expect($rows->pluck('id')->all())->toBe([$newer->id, $older->id]);
});

it('searches across action and description', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    createAuditLog(['action' => 'auth.login.succeeded', 'description' => 'Ada Lovelace logged in']);
    createAuditLog(['action' => 'course.published', 'description' => 'Grace Hopper published a course']);

    Livewire::test(AuditLogTable::class)
        ->set('search', 'Ada')
        ->assertSee('Ada Lovelace logged in')
        ->assertDontSee('Grace Hopper published a course');
});

it('filters by action, with options drawn from the real distinct actions recorded', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    createAuditLog(['action' => 'auth.login.succeeded', 'description' => 'First entry']);
    createAuditLog(['action' => 'course.published', 'description' => 'Second entry']);

    // Direct instantiation for the data-method call, same reason as above;
    // a separate Livewire::test() instance drives the actual set()/assertSee()
    // chain since that part needs the real component lifecycle.
    $standalone = new AuditLogTable;
    $standalone->mount();

    // Not a hardcoded list — exactly the two actions actually written above.
    expect($standalone->actionOptions())
        ->toBe(['auth.login.succeeded', 'course.published']);

    Livewire::test(AuditLogTable::class)
        ->set('actionFilter', 'auth.login.succeeded')
        ->assertSee('First entry')
        ->assertDontSee('Second entry');
});

it('renders "System" for a null-actor entry without erroring', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    createAuditLog(['user_id' => null, 'description' => 'A system-recorded event']);

    Livewire::test(AuditLogTable::class)
        ->assertSee('System')
        ->assertSee('A system-recorded event');
});

it('shows an empty state with no audit entries', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Livewire::test(AuditLogTable::class)->assertSee('No audit entries yet');
});

it('does not N+1 when listing entries with their actor names', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    foreach (User::factory()->count(10)->create() as $user) {
        createAuditLog(['user_id' => $user->id]);
    }

    DB::enableQueryLog();
    $first = new AuditLogTable;
    $first->mount();
    $first->rows()->each(fn (AuditLog $entry) => $entry->user?->name);
    $withTen = count(DB::getQueryLog());
    DB::flushQueryLog();

    // Flushed again immediately before the second measured call, not just
    // after the first — otherwise the 20 factory-cascaded user inserts below
    // land in the log too and this stops measuring what it claims to.
    foreach (User::factory()->count(20)->create() as $user) {
        createAuditLog(['user_id' => $user->id]);
    }
    DB::flushQueryLog();

    $second = new AuditLogTable;
    $second->mount();
    $second->rows()->each(fn (AuditLog $entry) => $entry->user?->name);
    $withThirty = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($withTen)->toBe($withThirty);
});

it('denies an instructor and a student from viewing the audit log', function (): void {
    createAuditLog();

    $instructor = User::factory()->instructor()->create();
    $this->actingAs($instructor)->get(route('admin.audit-log.index'))->assertForbidden();

    $student = User::factory()->student()->create();
    $this->actingAs($student)->get(route('admin.audit-log.index'))->assertForbidden();
});
