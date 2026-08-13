<?php

declare(strict_types=1);

use App\Enums\EmailStatus;
use App\Models\EmailLog;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Services\Mail\EmailLogger;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Phase 11 — every send is recorded (FR-MAIL-10, architecture.md §14)
|--------------------------------------------------------------------------
|
| WHY THIS TABLE EARNS ITS KEEP: when a student says "I never received my
| activation email", the only useful answer comes from a record of what the
| system tried to send and what happened to it. Without it, support is
| guesswork, and a silently misconfigured transport looks exactly like a
| working one.
|
| Logging is wired to Laravel's transport events rather than to individual
| mailables, so coverage does not depend on the author of a future email
| remembering to add a log call.
|
*/

it('records an email log entry when a notification is sent', function (): void {
    $user = User::factory()->create(['email' => 'student@example.test']);

    $user->notify(new PasswordChangedNotification);

    $log = EmailLog::query()->latest('id')->firstOrFail();

    expect($log->to_email)->toBe('student@example.test')
        ->and($log->mailable)->toBe(PasswordChangedNotification::class)
        ->and($log->subject)->toContain('password was changed');
});

it('marks the entry sent once the transport accepts the message', function (): void {
    $user = User::factory()->create();

    $user->notify(new PasswordChangedNotification);

    $log = EmailLog::query()->latest('id')->firstOrFail();

    /*
     * "Sent" means the transport accepted it — the furthest the application can
     * honestly claim to know. Whether it reached an inbox is a question only
     * the provider can answer.
     */
    expect($log->status)->toBe(EmailStatus::Sent)
        ->and($log->sent_at)->not->toBeNull();
});

it('logs each send separately when several emails go out', function (): void {
    $first = User::factory()->create(['email' => 'first@example.test']);
    $second = User::factory()->create(['email' => 'second@example.test']);

    $first->notify(new PasswordChangedNotification);
    $second->notify(new PasswordChangedNotification);

    /*
     * A worker interleaves sends, so the outcome of one must never be recorded
     * against another. The log id travels on the message itself for exactly
     * this reason — matching by recipient alone would misattribute under
     * concurrency.
     */
    expect(EmailLog::query()->where('to_email', 'first@example.test')->count())->toBe(1)
        ->and(EmailLog::query()->where('to_email', 'second@example.test')->count())->toBe(1);
});

/*
| ═════════════ LOGGING NEVER BREAKS DELIVERY ═════════════
*/
it('does not fail the send when the log cannot be written', function (): void {
    /*
     * Logging is observability, not delivery. If writing the row fails, the
     * email must still go out — and the transaction behind it must still
     * commit (AC-33). EmailLogger swallows its own failures into the
     * application log for this reason.
     */
    Mail::fake();

    $logger = app(EmailLogger::class);
    $log = new EmailLog; // unsaved: forcing a write failure downstream

    expect(fn () => $logger->markSent($log))->not->toThrow(Exception::class);
    expect(fn () => $logger->markFailed($log, 'transport exploded'))->not->toThrow(Exception::class);
});

it('truncates a very long transport error rather than failing to store it', function (): void {
    $log = EmailLog::factory()->create();

    app(EmailLogger::class)->markFailed($log, str_repeat('x', 5000));

    // A provider stack trace can run to kilobytes; the useful part is at the
    // front, and the row must still be storable.
    expect(mb_strlen((string) $log->refresh()->error))->toBeLessThanOrEqual(2000);
});

it('records the failure reason against the entry', function (): void {
    $log = EmailLog::factory()->create();

    app(EmailLogger::class)->markFailed($log, 'Connection refused by smtp.example.test');

    expect($log->refresh()->status)->toBe(EmailStatus::Failed)
        ->and($log->error)->toContain('Connection refused');
});
