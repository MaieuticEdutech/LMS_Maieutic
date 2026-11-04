<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| /up reports a stopped scheduler without taking the web app out of service
|--------------------------------------------------------------------------
|
| Order reconciliation, the backstop for a missed payment webhook, only runs
| while `schedule:run` is firing, and a dead scheduler otherwise looks healthy.
| Like the queue check, a stalled scheduler degrades `status` but never turns the
| response into a 503.
*/

it('reports the scheduler as unknown when no heartbeat has been seen', function (): void {
    Cache::forget(HealthController::SCHEDULER_HEARTBEAT_KEY);

    $this->getJson('/up')
        ->assertOk()
        ->assertJson(['status' => 'ok', 'checks' => ['scheduler' => 'unknown']]);
});

it('reports the scheduler as ok after a recent heartbeat', function (): void {
    Cache::put(HealthController::SCHEDULER_HEARTBEAT_KEY, now()->subSeconds(90)->getTimestamp(), 3600);

    $this->getJson('/up')
        ->assertOk()
        ->assertJson(['status' => 'ok', 'checks' => ['scheduler' => 'ok']]);
});

it('reports a stale heartbeat as degraded but still answers 200', function (): void {
    Cache::put(HealthController::SCHEDULER_HEARTBEAT_KEY, now()->subMinutes(10)->getTimestamp(), 3600);

    $this->getJson('/up')
        ->assertOk()
        ->assertJson(['status' => 'degraded', 'checks' => ['scheduler' => 'stalled']]);
});

it('registers a task that writes the heartbeat every minute', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->firstOrFail(static fn ($event): bool => $event->description === 'scheduler-heartbeat');

    expect($event->expression)->toBe('* * * * *');

    Cache::forget(HealthController::SCHEDULER_HEARTBEAT_KEY);
    $this->artisan('schedule:run');

    expect(Cache::get(HealthController::SCHEDULER_HEARTBEAT_KEY))->toBeInt();
});
