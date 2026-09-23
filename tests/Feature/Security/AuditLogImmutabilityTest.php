<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Audit log immutability — Phase 14, NFR-SEC-16, NFR-SEC-17
|--------------------------------------------------------------------------
|
| AuditLog.php's own docblock claims immutability is "enforced in code, not
| just by convention" — this proves that claim rather than trusting the
| comment, and additionally proves the DB-level trigger added in this pass
| (2026_09_23_075355_add_append_only_trigger_to_audit_logs_table.php),
| which nothing calls through Eloquent could ever have exercised.
|
*/

use App\Models\AuditLog;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use Illuminate\Support\Facades\DB;

it('refuses to update an entry through Eloquent', function (): void {
    $log = AuditLog::factory()->create(['action' => 'test.original']);

    expect(fn () => $log->update(['action' => 'test.tampered']))
        ->toThrow(LogicException::class, 'append-only');

    expect(AuditLog::query()->find($log->id)?->action)->toBe('test.original');
});

it('refuses to delete an entry through Eloquent', function (): void {
    $log = AuditLog::factory()->create();

    expect(fn () => $log->delete())->toThrow(LogicException::class, 'append-only');

    expect(AuditLog::query()->find($log->id))->not->toBeNull();
});

it('denies every ability except reading to every actor, including a super admin', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $log = AuditLog::factory()->create();
    $policy = new AuditLogPolicy;

    expect($policy->create($superAdmin))->toBeFalse()
        ->and($policy->update($superAdmin, $log))->toBeFalse()
        ->and($policy->delete($superAdmin, $log))->toBeFalse();
});

/*
| ═══════════ THE BACKSTOP: BLOCKED EVEN WHEN ELOQUENT IS BYPASSED ═══════════
|
| The application-level guards above are real, but they only fire when code
| goes through the AuditLog model. A raw query builder call, a database
| client, or a future migration/seeder bug goes around all of it — which is
| exactly what this proves the trigger stops.
*/
it('refuses an update at the database level even when Eloquent is bypassed entirely', function (): void {
    $log = AuditLog::factory()->create(['action' => 'test.original']);

    expect(fn () => DB::table('audit_logs')
        ->where('id', $log->id)
        ->update(['action' => 'test.tampered']))
        ->toThrow(Illuminate\Database\QueryException::class, 'append-only');
});

it('refuses a delete at the database level even when Eloquent is bypassed entirely', function (): void {
    $log = AuditLog::factory()->create();

    // No follow-up query in this test: Postgres aborts the whole wrapping
    // transaction once the trigger raises, so any further statement here
    // would fail with "current transaction is aborted" rather than proving
    // anything about the row — the throw itself is the proof nothing committed.
    expect(fn () => DB::table('audit_logs')->where('id', $log->id)->delete())
        ->toThrow(Illuminate\Database\QueryException::class, 'append-only');
});

it('still allows the one legitimate UPDATE: user_id -> NULL when the actor is deleted', function (): void {
    // architecture.md's own reasoning for ON DELETE SET NULL rather than
    // CASCADE on user_id: deleting a user must never erase the record of
    // what they did. That FK action IS an UPDATE under the hood, so the
    // trigger has to let exactly this one shape through, not just
    // 2026_08_12_100003's own test proving the FK relationship exists.
    $actor = User::factory()->create();
    $log = AuditLog::factory()->create(['user_id' => $actor->id]);

    $actor->forceDelete();

    expect(AuditLog::query()->find($log->id)?->user_id)->toBeNull();
});
