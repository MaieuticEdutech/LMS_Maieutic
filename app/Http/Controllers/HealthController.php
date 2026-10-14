<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Queue\QueueHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Liveness / readiness endpoint.
 *
 * Hard dependencies — database, cache, content storage. If one is unreachable
 * the response is 503, so a load balancer or container healthcheck can act on
 * the status code alone without parsing the body.
 *
 * ADVISORY — the queue. A queue that is not being consumed is a stopped worker,
 * which is a separate Coolify resource from the web app. It is reported as
 * `stalled` and turns `status` to `degraded`, but the HTTP code stays 200:
 * otherwise a dead worker could get a healthy web container pulled out of
 * service. Uptime monitoring (Phase 17) should alert on `status != ok`.
 *
 * `unknown` means the queue is not the database driver, so its age cannot be
 * read here. That is reported honestly and does not degrade the status.
 *
 * SECURITY: the body reports only reachable/unreachable per dependency. It must
 * never leak connection strings, credentials, versions or exception messages —
 * this endpoint is typically unauthenticated (NFR-DATA-03).
 */
final class HealthController extends Controller
{
    /**
     * The oldest waiting job may be this old before the worker is considered
     * stopped. Jobs here are mail, grading and reconciliation: seconds of work.
     */
    private const QUEUE_STALLED_AFTER_SECONDS = 300;

    public function __invoke(QueueHealthService $queue): JsonResponse
    {
        $checks = [
            'database' => $this->check(static function (): void {
                DB::connection()->getPdo();
                DB::select('select 1');
            }),
            'cache' => $this->check(static function (): void {
                $key = 'health:'.bin2hex(random_bytes(8));
                Cache::put($key, 'ok', 10);

                if (Cache::get($key) !== 'ok') {
                    throw new RuntimeException('Cache read-back failed.');
                }

                Cache::forget($key);
            }),
            'storage' => $this->check(static function (): void {
                $disk = Storage::disk(config()->string('lms.disks.content'));
                $path = 'health/'.bin2hex(random_bytes(8)).'.tmp';

                $disk->put($path, 'ok');

                if ($disk->get($path) !== 'ok') {
                    throw new RuntimeException('Storage read-back failed.');
                }

                $disk->delete($path);
            }),
        ];

        $healthy = ! in_array(false, $checks, true);
        $queueState = $this->queueState($queue);

        return response()->json([
            'status' => $healthy && $queueState !== 'stalled' ? 'ok' : 'degraded',
            'checks' => [
                ...array_map(
                    static fn (bool $ok): string => $ok ? 'ok' : 'unreachable',
                    $checks,
                ),
                'queue' => $queueState,
            ],
        ], $healthy ? 200 : 503);
    }

    /**
     * @return 'ok'|'stalled'|'unknown'
     */
    private function queueState(QueueHealthService $queue): string
    {
        try {
            $oldest = $queue->oldestPendingSeconds();
        } catch (Throwable $e) {
            report($e);

            return 'unknown';
        }

        if ($oldest === null) {
            // Nothing waiting, or a driver whose age cannot be read. Only the
            // first is "ok"; the service returns null for both, so a
            // non-database queue is told apart here.
            $connection = config()->string('queue.default');

            return config()->string("queue.connections.{$connection}.driver") === 'database' ? 'ok' : 'unknown';
        }

        return $oldest > self::QUEUE_STALLED_AFTER_SECONDS ? 'stalled' : 'ok';
    }

    /**
     * Run a dependency probe, converting any failure into false.
     *
     * The exception is deliberately swallowed rather than surfaced: the detail
     * belongs in the application log, not in an unauthenticated HTTP response.
     */
    private function check(callable $probe): bool
    {
        try {
            $probe();

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
