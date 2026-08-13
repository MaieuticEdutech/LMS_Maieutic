<?php

declare(strict_types=1);

use App\Listeners\AlertOnFailedJob;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Phase 11 — queue infrastructure (architecture.md §13)
|--------------------------------------------------------------------------
|
| Four named queues in priority order, so a backlog of report exports can never
| delay a payment webhook or an activation email. The names are configuration
| rather than literals in job classes: a job pushed onto a queue no worker
| drains is invisible, and fails by doing nothing at all.
|
*/

it('defines the four named queues from architecture.md §13', function (): void {
    expect(config()->string('lms.queues.critical'))->toBe('critical')
        ->and(config()->string('lms.queues.mail'))->toBe('mail')
        ->and(config()->string('lms.queues.default'))->toBe('default')
        ->and(config()->string('lms.queues.low'))->toBe('low');
});

it('orders the worker drain list by priority', function (): void {
    /*
     * Order is the whole point. Workers drain left to right, so `critical`
     * (payments, enrollment) is always served before `low` (exports, media
     * cleanup). Deployment builds the supervisor --queue argument from this
     * list, so it is the single source of truth rather than a value repeated
     * in a process-manager config where it would drift.
     */
    expect(config()->array('lms.queues.priority'))
        ->toBe(['critical', 'mail', 'default', 'low']);
});

it('keeps the mail job timeout below the queue retry window', function (): void {
    /*
     * If a job can still be running when retry_after elapses, the queue hands
     * it to a SECOND worker and the recipient gets the email twice. This is
     * the invariant that prevents it, and it is easy to break by raising the
     * timeout without a thought for the connection.
     */
    $timeout = config()->integer('lms.mail.timeout');
    $retryAfter = config()->integer('queue.connections.database.retry_after');

    expect($timeout)->toBeLessThan($retryAfter);
});

it('retries mail with a backoff rather than giving up on first failure', function (): void {
    // Transport failures are usually transient (FR-MAIL-06).
    expect(config()->integer('lms.mail.tries'))->toBeGreaterThan(1)
        ->and(config()->array('lms.mail.backoff'))->not->toBeEmpty();
});

/*
| ═════════════ THE TABLES THE QUEUE DEPENDS ON ═════════════
*/
it('has the jobs, job_batches and failed_jobs tables', function (string $table): void {
    expect(Schema::hasTable($table))->toBeTrue();
})->with(['jobs', 'job_batches', 'failed_jobs']);

/*
| ═════════════ A FAILED JOB IS AN INCIDENT, NOT A LOG LINE ═════════════
*/

/**
 * A minimal real Job for the failure path.
 *
 * A concrete implementation rather than a mock: JobFailed's constructor is
 * typed against the Job CONTRACT, and only three of its methods matter here.
 * Implementing them plainly keeps the test readable and correctly typed.
 */
function fakeJob(int $attempts): Job
{
    return new class($attempts) extends SyncJob
    {
        public function __construct(private readonly int $attemptCount)
        {
            parent::__construct(app(), '{}', 'sync', 'mail');
        }

        public function resolveName(): string
        {
            return 'App\Jobs\SomeJob';
        }

        public function getQueue(): string
        {
            return 'mail';
        }

        public function attempts(): int
        {
            return $this->attemptCount;
        }
    };
}
it('raises a critical alert when a job exhausts its retries', function (): void {
    /*
     * By the time JobFailed fires, the work is NOT going to happen on its own.
     * For mail that means a student never received something the system
     * promised them; from Phase 12, for a payment webhook, it means someone
     * paid and did not get access. Both are silent without this.
     */
    Log::shouldReceive('critical')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'failed')
                && $context['job'] === 'App\Jobs\SomeJob';
        });

    $job = fakeJob(attempts: 3);

    (new AlertOnFailedJob)->handle(
        new JobFailed('database', $job, new RuntimeException('transport unreachable')),
    );
});

it('keeps job payloads out of the alert', function (): void {
    /*
     * Payloads carry email addresses and, from Phase 12, order and payment
     * identifiers. An alerting sink is not an appropriate destination for
     * personal or financial data (NFR-DATA-03).
     */
    Log::shouldReceive('critical')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => ! array_key_exists('payload', $context)
            && ! array_key_exists('data', $context));

    $job = fakeJob(attempts: 1);

    (new AlertOnFailedJob)->handle(
        new JobFailed('database', $job, new RuntimeException('boom')),
    );
});

it('registers the failed-job listener', function (): void {
    // Registration is explicit in AppServiceProvider precisely because
    // auto-discovery fails silently — an unregistered alert listener means
    // failures happen with nobody told.
    expect(Event::hasListeners(JobFailed::class))->toBeTrue();
});
