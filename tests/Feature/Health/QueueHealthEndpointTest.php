<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| /up reports a stopped queue worker without taking the web app out of service
|--------------------------------------------------------------------------
|
| The worker is a separate resource from the web container. A stalled queue is
| therefore ADVISORY: status goes to "degraded" but the HTTP code stays 200, so
| a container healthcheck on /up can never pull a healthy web app out of
| rotation because the worker died. The hard dependencies still return 503.
*/

beforeEach(function (): void {
    Storage::fake(config()->string('lms.disks.content'));
});

function queueJobAvailableSecondsAgo(int $seconds): void
{
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->getTimestamp() - $seconds,
        'created_at' => now()->getTimestamp() - $seconds,
    ]);
}

it('reports the queue as unknown when it is not the database driver', function (): void {
    // The test suite runs the sync driver, whose age cannot be read.
    $this->getJson('/up')
        ->assertOk()
        ->assertJson(['status' => 'ok', 'checks' => ['queue' => 'unknown']]);
});

describe('with the database queue', function (): void {
    beforeEach(function (): void {
        config()->set('queue.default', 'database');
    });

    it('reports ok when nothing is waiting', function (): void {
        $this->getJson('/up')
            ->assertOk()
            ->assertJson(['status' => 'ok', 'checks' => ['queue' => 'ok']]);
    });

    it('reports ok for a job that has only just been queued', function (): void {
        queueJobAvailableSecondsAgo(20);

        $this->getJson('/up')
            ->assertOk()
            ->assertJson(['status' => 'ok', 'checks' => ['queue' => 'ok']]);
    });

    it('reports a stalled queue as degraded but still answers 200', function (): void {
        queueJobAvailableSecondsAgo(900);

        $this->getJson('/up')
            ->assertOk()
            ->assertJson(['status' => 'degraded', 'checks' => ['queue' => 'stalled']]);
    });

    it('does not mention job names or counts in the body', function (): void {
        queueJobAvailableSecondsAgo(900);

        $body = (string) $this->getJson('/up')->getContent();

        expect($body)->not->toContain('default')
            ->not->toContain('payload')
            ->not->toContain('900');
    });
});
