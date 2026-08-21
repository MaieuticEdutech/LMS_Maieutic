<?php

declare(strict_types=1);

use Illuminate\Foundation\DevCommands;

/*
|--------------------------------------------------------------------------
| `composer dev` must drain every queue, not just `default`
|--------------------------------------------------------------------------
|
| Laravel's dev runner registers `queue:listen` with no --queue argument, so
| it processes `default` alone. This application dispatches across four
| (config/lms.php): critical, mail, default, low.
|
| The consequence in local development was that every outbound email queued
| onto `mail` and was never sent. Nothing failed, nothing reached failed_jobs
| — the rows just accumulated, so an entire feature looked built and did
| nothing. That silence is why this is asserted rather than left to a comment.
|
*/

/**
 * The registered dev command line for a given process name.
 *
 * firstWhere() is nullable; indexing it directly would report "undefined
 * offset" when the real failure is "that process was never registered".
 */
function devCommandLine(string $name): string
{
    $entry = collect(DevCommands::commands())->firstWhere('name', $name);

    expect($entry)->not->toBeNull("no dev command named '{$name}' is registered");

    return (string) ($entry['command'] ?? '');
}
it('registers a worker covering every configured queue', function (): void {
    $names = collect(DevCommands::commands())->pluck('name');

    expect($names)->toContain('queues');

    $command = devCommandLine('queues');

    foreach (config()->array('lms.queues.priority') as $queue) {
        expect($command)->toContain($queue);
    }
});

it('drops the stock single-queue worker', function (): void {
    // Left in place it would poll `default` alongside ours — the same work
    // picked up twice and a runner log nobody can read.
    expect(collect(DevCommands::commands())->pluck('name'))->not->toContain('queue');
});

it('drains in the priority order config declares', function (): void {
    // config/lms.php calls this order the single source of truth, and Phase 16
    // builds the supervisor's --queue argument from it. Local and production
    // must not drift apart.
    $command = devCommandLine('queues');

    expect($command)->toContain('--queue='.implode(',', config()->array('lms.queues.priority')));
});

it('puts critical ahead of everything else', function (): void {
    // Money and access first: a backlog of exports must never delay a payment
    // webhook (config/lms.php).
    $priority = config()->array('lms.queues.priority');

    // Flipped to value => position, so the comparison is between two ints
    // rather than array_search()'s int|false.
    $position = array_flip($priority);

    expect($priority[0])->toBe('critical')
        ->and($position['mail'] ?? PHP_INT_MAX)->toBeLessThan($position['low'] ?? PHP_INT_MAX);
});
