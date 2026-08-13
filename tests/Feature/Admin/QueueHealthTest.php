<?php

declare(strict_types=1);

use App\Livewire\Admin\QueueHealth;
use App\Models\User;
use App\Services\Queue\QueueHealthService;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Phase 11 · Queue health and failed-job recovery
|--------------------------------------------------------------------------
|
| AlertOnFailedJob shouts when a job exhausts its retries. This is the other
| half — the screen where somebody can then do something about it.
|
| What a failure MEANS is never cosmetic: a mail job here is a student who
| never got their activation link; from Phase 12 a payment job is somebody who
| paid and did not get access. So the tests care about two things: that the
| panel tells the truth about what it can and cannot see, and that retry
| actually re-queues rather than merely clearing the list.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();

    /*
     * The suite runs on the `sync` driver, where there is no `jobs` table and
     * therefore no depth to read — correct behaviour, and asserted in its own
     * test below. Everything else here is about the database queue this
     * application actually uses in development, so it is selected explicitly
     * rather than inherited from phpunit.xml.
     *
     * It also makes the retry assertions real: pushRaw writes a row, and the
     * test reads it. Queue::fake() does not record raw pushes at all, so a
     * faked retry would assert nothing.
     */
    config()->set('queue.default', 'database');

    $this->failer = app(FailedJobProviderInterface::class);

    /**
     * Record a failure the way the framework does, so retry() reads a real
     * payload rather than a shape invented by the test.
     */
    $this->fail = function (string $displayName = 'App\\Notifications\\EnrollmentGrantedNotification', string $queue = 'mail'): string {
        $uuid = (string) Str::uuid();

        $this->failer->log('database', $queue, (string) json_encode([
            'uuid' => $uuid,
            'displayName' => $displayName,
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => ['commandName' => $displayName],
        ]), new RuntimeException('Connection to smtp.example.test refused'));

        return $uuid;
    };
});

/*
| ═══════════════ ACCESS ═══════════════
*/
it('is closed to everyone but a super admin', function (string $factoryState): void {
    $user = User::factory()->{$factoryState}()->create();

    $this->actingAs($user)->get(route('admin.queue-health.index'))->assertForbidden();
})->with(['student', 'instructor']);

it('refuses a retry from a non-super-admin, not merely the page', function (): void {
    ($this->fail)();

    /*
     * The route middleware is the coarse gate. This asserts the component's
     * own check, because retry re-runs arbitrary queued work and one mistaken
     * route registration should not be all that stands in front of it.
     */
    $this->actingAs(User::factory()->student()->create());

    Livewire::test(QueueHealth::class)->assertForbidden();
});

it('opens for a super admin', function (): void {
    $this->actingAs($this->admin)->get(route('admin.queue-health.index'))->assertOk();
});

/*
| ═══════════════ WHAT IT SHOWS ═══════════════
*/
it('lists a failed job with its reason', function (): void {
    ($this->fail)();

    $this->actingAs($this->admin);

    Livewire::test(QueueHealth::class)
        ->assertSee('EnrollmentGrantedNotification')
        ->assertSee('Connection to smtp.example.test refused');
});

it('shows the first line of the exception, not the whole trace', function (): void {
    ($this->fail)();

    $failed = app(QueueHealthService::class)->failed();

    expect($failed[0]['exception'])->toContain('Connection to smtp.example.test refused')
        // A stack trace in a table cell is unreadable; the full text stays in
        // failed_jobs for anyone who needs it.
        ->and($failed[0]['exception'])->not->toContain('#0 ');
});

it('reports every configured queue, including the empty ones', function (): void {
    $pending = app(QueueHealthService::class)->pending();

    if ($pending === null) {
        $this->fail('Depth must be readable on the database driver — null means the driver check is wrong.');
    }

    // A queue missing from the list reads as "not running" rather than "idle",
    // and those are very different things at three in the morning.
    expect($pending)->toHaveKeys(['critical', 'mail', 'default', 'low'])
        ->and($pending['mail'])->toBe(0);
});

it('admits it cannot read depth on a driver that has no jobs table', function (): void {
    config()->set('queue.default', 'redis');

    /*
     * Production is Redis. A panel confidently reporting "0 pending" against a
     * backed-up Redis queue would be worse than one saying it cannot see —
     * this is the difference between a dashboard and a reassurance.
     */
    expect(app(QueueHealthService::class)->pending())->toBeNull()
        ->and(app(QueueHealthService::class)->oldestPendingSeconds())->toBeNull();

    $this->actingAs($this->admin);

    Livewire::test(QueueHealth::class)->assertSee('not readable on this driver');
});

it('reports how long the oldest job has been waiting', function (): void {
    // Depth alone cannot tell a busy system from a stopped worker.
    DB::table('jobs')->insert([
        'queue' => 'mail',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subMinutes(10)->getTimestamp(),
        'created_at' => now()->subMinutes(10)->getTimestamp(),
    ]);

    expect(app(QueueHealthService::class)->oldestPendingSeconds())->toBeGreaterThanOrEqual(600);
});

it('says nothing has failed when nothing has', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(QueueHealth::class)->assertSee('Nothing has failed');
});

/*
| ═══════════════ RECOVERY ═══════════════
*/
it('pushes a retried job back onto its own queue', function (): void {
    $uuid = ($this->fail)(queue: 'mail');

    expect(app(QueueHealthService::class)->retry($uuid))->toBeTrue();

    // Back on `mail`, not on whatever the default happens to be — a
    // congratulations email must never be re-queued ahead of an enrollment.
    expect(DB::table('jobs')->where('queue', 'mail')->count())->toBe(1);
});

it('removes a job from the failed list once it has been re-queued', function (): void {
    $uuid = ($this->fail)();

    app(QueueHealthService::class)->retry($uuid);

    expect($this->failer->find($uuid))->toBeNull();
});

it('gives the retried job a fresh identity', function (): void {
    $uuid = ($this->fail)();

    app(QueueHealthService::class)->retry($uuid);

    $payload = json_decode((string) DB::table('jobs')->value('payload'), true);

    // A retry is a NEW attempt, not a duplicate carrying the identity of a
    // record that is about to be deleted.
    expect($payload)->toBeArray()
        ->and($payload['uuid'])->not->toBe($uuid)
        // ...and it is still the same job, not an empty shell.
        ->and($payload['displayName'])->toBe('App\\Notifications\\EnrollmentGrantedNotification');
});

it('answers plainly when a job has already been handled by somebody else', function (): void {
    // Two people looking at the same incident will press retry on the same row.
    expect(app(QueueHealthService::class)->retry((string) Str::uuid()))->toBeFalse();

    $this->actingAs($this->admin);

    Livewire::test(QueueHealth::class)
        ->call('retry', (string) Str::uuid())
        ->assertHasNoErrors();
});

it('discards a job without running it', function (): void {
    $uuid = ($this->fail)();

    $this->actingAs($this->admin);

    Livewire::test(QueueHealth::class)->call('forget', $uuid);

    expect($this->failer->find($uuid))->toBeNull()
        // Discard means discard. Anything queued here would be the opposite of
        // what the operator asked for.
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('counts the failures it is showing', function (): void {
    ($this->fail)();
    ($this->fail)();

    expect(app(QueueHealthService::class)->failedCount())->toBe(2);
});
