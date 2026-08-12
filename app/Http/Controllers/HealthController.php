<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Liveness / readiness endpoint.
 *
 * Phase 1 delivers database, cache and content-storage checks. Redis and queue
 * depth checks are added in Phase 11 when those become real dependencies, and
 * this endpoint is what uptime monitoring watches in Phase 17
 * (architecture.md §20).
 *
 * Returns 200 when every dependency is reachable, 503 otherwise, so a load
 * balancer or monitor can act on the status code alone without parsing the body.
 *
 * SECURITY: the body reports only reachable/unreachable per dependency. It must
 * never leak connection strings, credentials, versions or exception messages —
 * this endpoint is typically unauthenticated (NFR-DATA-03).
 */
final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
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

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => array_map(
                static fn (bool $ok): string => $ok ? 'ok' : 'unreachable',
                $checks,
            ),
        ], $healthy ? 200 : 503);
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
