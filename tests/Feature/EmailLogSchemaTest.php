<?php

declare(strict_types=1);

use App\Enums\EmailStatus;
use App\Models\EmailLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Phase 3 (Track C) — email_logs schema and model invariants
|--------------------------------------------------------------------------
|
| Guards the database-level protections the application relies on but never
| re-checks at runtime (architecture.md §6.4, ADR-012).
|
*/

it('creates the email_logs table', function (): void {
    expect(Schema::hasTable('email_logs'))->toBeTrue();
});

/*
| CHECK CONSTRAINT (ADR-012). The database refuses an illegal status even if
| application code is bypassed.
*/
it('rejects an invalid status at the database level', function (): void {
    $log = EmailLog::factory()->create();

    expect(fn () => DB::table('email_logs')->where('id', $log->id)->update(['status' => 'bounced']))
        ->toThrow(QueryException::class);
});

it('accepts every email status the application can produce', function (EmailStatus $status): void {
    $log = EmailLog::factory()->create();
    $log->forceFill(['status' => $status])->save();

    expect($log->refresh()->status)->toBe($status);
})->with('email statuses');

/*
| NO FK TO users — a log entry must not depend on the account it might be
| describing (architecture.md §6.4's nullable-password mechanism means an
| email can legitimately be sent before an account exists).
*/
it('accepts an email address with no corresponding user account', function (): void {
    $log = EmailLog::factory()->create(['to_email' => 'not-yet-a-user@example.com']);

    expect($log->refresh()->to_email)->toBe('not-yet-a-user@example.com');
});

/*
| CASTS AND STATE SHAPES.
*/
it('casts status, sent_at and context correctly', function (): void {
    $log = EmailLog::factory()->sent()->create([
        'context' => ['course_id' => 42, 'purchase_id' => 7],
    ]);

    expect($log->status)->toBe(EmailStatus::Sent)
        ->and($log->sent_at?->isToday())->toBeTrue()
        ->and($log->context)->toBe(['course_id' => 42, 'purchase_id' => 7]);
});

it('records a failure without a sent_at timestamp', function (): void {
    $log = EmailLog::factory()->failed()->create();

    expect($log->status)->toBe(EmailStatus::Failed)
        ->and($log->error)->not->toBeNull()
        ->and($log->sent_at)->toBeNull();
});

dataset('email statuses', fn (): array => EmailStatus::cases());
