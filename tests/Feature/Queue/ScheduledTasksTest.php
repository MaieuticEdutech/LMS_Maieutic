<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;

/*
|--------------------------------------------------------------------------
| Phase 11 — scheduled tasks are registered (architecture.md §13, §23)
|--------------------------------------------------------------------------
|
| Production runs ONE cron entry (`schedule:run` every minute) and everything
| recurring hangs off it. A task that is not registered here simply never runs,
| with no error anywhere — which is why this is worth asserting rather than
| assuming.
|
| Only the tasks whose commands exist today are registered. The remaining
| entries from architecture.md §13 belong to phases that are not built yet and
| are deliberately NOT stubbed: a scheduled entry pointing at a missing command
| fails silently every minute while looking configured. routes/console.php
| carries the full checklist.
|
*/

/**
 * @return list<string>
 */
function scheduledCommands(): array
{
    return array_values(
        array_map(
            static fn (ScheduledEvent $event): string => (string) $event->command,
            app(Schedule::class)->events(),
        ),
    );
}

it('registers the queue and mail hygiene tasks', function (string $command): void {
    expect(collect(scheduledCommands())->contains(fn (string $c): bool => str_contains($c, $command)))
        ->toBeTrue("Expected a scheduled task running [{$command}].");
})->with([
    'queue:prune-failed',
    'queue:prune-batches',
    'auth:clear-resets',
]);

it('keeps failed jobs long enough to investigate an incident', function (): void {
    /*
     * A week, not the framework's 24 hours. A payment or enrollment failure is
     * often reported by the student rather than by monitoring, and that report
     * can arrive days later — by which time the evidence must still exist.
     */
    $prune = collect(scheduledCommands())
        ->first(static fn (string $c): bool => str_contains($c, 'queue:prune-failed'));

    expect($prune)->toContain('--hours=168');
});

it('runs each scheduled task on only one server', function (): void {
    /*
     * Production runs more than one application server (architecture.md §22).
     * Without onOneServer every box runs every task, and two machines pruning
     * concurrently is a race with no upside.
     */
    $events = app(Schedule::class)->events();

    expect($events)->not->toBeEmpty();

    foreach ($events as $event) {
        expect($event->onOneServer)->toBeTrue(
            "Scheduled task [{$event->command}] must be limited to one server.",
        );
    }
});

it('does not schedule commands that do not exist yet', function (): void {
    /*
     * The guard against stubbing ahead. Each of these belongs to an unbuilt
     * phase; registering one now would create a task that fails silently every
     * time the scheduler runs, which reads as "configured" while doing nothing.
     */
    $commands = collect(scheduledCommands());

    foreach (['orders:reconcile', 'orders:cancel-abandoned', 'attempts:expire', 'enrollments:expire'] as $future) {
        expect($commands->contains(fn (string $c): bool => str_contains($c, $future)))
            ->toBeFalse("[{$future}] belongs to a later phase and must not be scheduled yet.");
    }
});
