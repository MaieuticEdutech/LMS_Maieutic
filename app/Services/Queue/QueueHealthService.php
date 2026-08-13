<?php

declare(strict_types=1);

namespace App\Services\Queue;

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What the queue is doing, and what to do about the parts of it that went
 * wrong (phases.md Phase 11, architecture.md §13, §20).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * A FAILED JOB IS A PROMISE THIS SYSTEM MADE AND DID NOT KEEP.
 *
 * By the time a job reaches `failed_jobs` it has exhausted its retries and
 * will not run on its own. What that MEANS depends on the job, and none of the
 * meanings are cosmetic:
 *
 *   a mail job      — a student never received their activation link
 *   a progress job  — a course percentage is wrong and will stay wrong
 *   a payment job   — from Phase 12, someone paid and did not get access
 *
 * AlertOnFailedJob shouts when it happens. This is the other half: the screen
 * where somebody can see what is stuck and press retry. An alert with no
 * recovery path is a notification that something is broken and nothing can be
 * done from here.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * The business logic lives here rather than in the Livewire component
 * (Rule 16) — retrying a job is a system operation, not a rendering concern,
 * and it must be testable without a render cycle.
 */
final class QueueHealthService
{
    public function __construct(
        private readonly FailedJobProviderInterface $failer,
        private readonly QueueFactory $queue,
    ) {}

    /**
     * Pending job counts per named queue.
     *
     * ═════════════════════════════════════════════════════════════════════
     * RETURNS NULL WHEN THE DEPTH CANNOT BE KNOWN, RATHER THAN ZERO.
     *
     * These counts come from the `jobs` table, which only exists on the
     * database driver. Production is Redis (§3), where this query would return
     * nothing — and a panel confidently reporting "0 pending" on a backed-up
     * Redis queue is worse than one admitting it cannot see. The view says so
     * in words.
     * ═════════════════════════════════════════════════════════════════════
     *
     * @return array<string, int>|null
     */
    public function pending(): ?array
    {
        if (! $this->readsFromDatabase()) {
            return null;
        }

        /** @var array<string, int> $counts */
        $counts = DB::table('jobs')
            ->selectRaw('queue, count(*) as aggregate')
            ->groupBy('queue')
            ->pluck('aggregate', 'queue')
            ->all();

        $pending = [];

        // Every configured queue appears, including the empty ones. A queue
        // missing from the list reads as "not running" rather than "idle",
        // and those are very different things at three in the morning.
        foreach ($this->queueNames() as $name) {
            $pending[$name] = (int) ($counts[$name] ?? 0);
        }

        return $pending;
    }

    /**
     * How many jobs have been sitting in the queue longest, in seconds.
     *
     * The single most useful number about a queue after its depth: a hundred
     * jobs that arrived a second ago is a busy system, and one job that has
     * been waiting an hour is a stopped worker. Depth alone cannot tell them
     * apart.
     */
    public function oldestPendingSeconds(): ?int
    {
        if (! $this->readsFromDatabase()) {
            return null;
        }

        $availableAt = DB::table('jobs')->min('available_at');

        if ($availableAt === null) {
            return null;
        }

        return max(0, now()->getTimestamp() - (int) $availableAt);
    }

    /**
     * Recent failures, newest first.
     *
     * @return list<array{uuid: string, job: string, queue: string, connection: string, failed_at: string, exception: string}>
     */
    public function failed(int $limit = 50): array
    {
        $rows = collect($this->failer->all())
            ->take($limit)
            ->map(fn (object $row): array => [
                'uuid' => (string) ($row->uuid ?? ''),
                'job' => $this->displayName($row),
                'queue' => (string) ($row->queue ?? 'default'),
                'connection' => (string) ($row->connection ?? ''),
                'failed_at' => (string) ($row->failed_at ?? ''),
                'exception' => $this->firstLineOf((string) ($row->exception ?? '')),
            ])
            ->values()
            ->all();

        /** @var list<array{uuid: string, job: string, queue: string, connection: string, failed_at: string, exception: string}> $rows */
        return $rows;
    }

    public function failedCount(): int
    {
        return count($this->failer->all());
    }

    /**
     * Push a failed job back onto its queue.
     *
     * ═════════════════════════════════════════════════════════════════════
     * SAFE ONLY BECAUSE EVERY JOB IN THIS SYSTEM IS IDEMPOTENT (FR-SYS-04).
     *
     * A retry can re-run work that partially succeeded before it failed. That
     * is acceptable here because each job derives its result rather than
     * incrementing it, and `GrantEnrollment` returns the existing enrollment
     * instead of creating a second one. It would NOT be acceptable in a system
     * where re-running meant charging a card twice — which is exactly why the
     * idempotency rule is a rule rather than a habit.
     * ═════════════════════════════════════════════════════════════════════
     *
     * Returns false when the uuid is unknown: an operator pressing retry on a
     * row somebody else already handled should get a plain "nothing to do",
     * not an exception.
     */
    public function retry(string $uuid): bool
    {
        $job = $this->failer->find($uuid);

        if ($job === null) {
            return false;
        }

        /*
         * The failer returns a bare stdClass whose shape is the failed_jobs
         * row, so its properties are read through get_object_vars() rather
         * than accessed directly — an undefined-property read here would be a
         * fatal on a driver that names its columns differently.
         */
        $row = get_object_vars($job);

        $payload = json_decode($this->stringOf($row, 'payload'), true);

        if (! is_array($payload)) {
            return false;
        }

        // The uuid is reset so the retried job is a NEW attempt rather than a
        // duplicate carrying the identity of a record that is about to be
        // deleted.
        $payload['uuid'] = (string) Str::uuid();

        $connection = $this->stringOf($row, 'connection');

        $this->queue
            ->connection($connection === '' ? null : $connection)
            ->pushRaw(
                (string) json_encode($payload),
                $this->stringOf($row, 'queue') ?: null,
            );

        // Forgotten only AFTER the push succeeded. The other order would lose
        // the job entirely if pushing threw — a recovery tool that can destroy
        // the thing it is recovering is worse than no tool.
        $this->failer->forget($uuid);

        return true;
    }

    /**
     * Discard a failed job without running it.
     *
     * The honest option for a failure that is understood and not worth
     * repeating — a mail to an address that no longer exists, say. Without it
     * the only way to clear the list is to retry things that will fail again,
     * which trains people to ignore the list.
     */
    public function forget(string $uuid): bool
    {
        return $this->failer->forget($uuid);
    }

    /**
     * One column of a failed_jobs row as a string, or '' if it is absent.
     *
     * @param  array<string, mixed>  $row
     */
    private function stringOf(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * The named queues this system uses, in drain order.
     *
     * @return list<string>
     */
    private function queueNames(): array
    {
        /** @var array<string, mixed> $configured */
        $configured = config()->array('lms.queues', []);

        $names = [];

        foreach ($configured as $value) {
            // The config block also carries a `worker_order` list; only the
            // scalar entries are queue names.
            if (is_string($value) && ! in_array($value, $names, true)) {
                $names[] = $value;
            }
        }

        return $names;
    }

    private function readsFromDatabase(): bool
    {
        $connection = config()->string('queue.default');

        return config()->string("queue.connections.{$connection}.driver") === 'database';
    }

    private function displayName(object $row): string
    {
        $payload = json_decode((string) ($row->payload ?? ''), true);

        if (is_array($payload) && isset($payload['displayName']) && is_string($payload['displayName'])) {
            return $payload['displayName'];
        }

        return 'unknown job';
    }

    /**
     * The exception's first line — its class and message.
     *
     * A full stack trace in a table cell is unreadable, and the first line is
     * what identifies the failure. The complete trace stays in `failed_jobs`
     * for anyone who needs it.
     */
    private function firstLineOf(string $exception): string
    {
        $line = strtok($exception, "\n");

        return $line === false ? '' : $line;
    }
}
