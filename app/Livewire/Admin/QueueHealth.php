<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\Queue\QueueHealthService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Queue health and failed-job recovery (phases.md Phase 11 — "admin:
 * failed-jobs / queue-health panel").
 *
 * AlertOnFailedJob shouts when a job exhausts its retries. This is where
 * somebody can then do something about it. An alert with no recovery path
 * tells an operator the system is broken and offers them nothing.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * AUTHORISED BY ROLE, NOT BY POLICY, AND THAT IS DELIBERATE.
 *
 * Every other admin screen authorises against a model — a policy answers "may
 * this user touch THIS record". There is no model here: `jobs` and
 * `failed_jobs` are framework tables with no Eloquent model and deliberately
 * none, since nothing in this application should be writing to them through
 * an ORM.
 *
 * So the check is the role itself, made explicitly in mount() rather than left
 * to the route's middleware. Middleware answers "may this kind of user be
 * here"; leaving it as the only check would mean one mistaken route
 * registration exposes a screen that can re-run arbitrary queued work
 * (architecture.md §8.2).
 * ═════════════════════════════════════════════════════════════════════════
 *
 * All the behaviour lives in QueueHealthService (Rule 16); this component
 * orchestrates and renders.
 */
#[Layout('layouts.admin', [
    'breadcrumbs' => [
        ['label' => 'Administration', 'url' => '/admin'],
        ['label' => 'Queue health', 'url' => null],
    ],
])]
final class QueueHealth extends Component
{
    public function mount(): void
    {
        $this->authoriseOperator();
    }

    public function retry(string $uuid): void
    {
        $this->authoriseOperator();

        $retried = app(QueueHealthService::class)->retry($uuid);

        // "Nothing to do" rather than an error: two people looking at an
        // incident together will press this on the same row.
        session()->flash('status', $retried
            ? 'Job pushed back onto its queue. It will run when a worker next picks it up.'
            : 'That job is no longer in the failed list — someone may have already handled it.');
    }

    public function forget(string $uuid): void
    {
        $this->authoriseOperator();

        app(QueueHealthService::class)->forget($uuid);

        session()->flash('status', 'Failed job discarded. It will not run.');
    }

    public function render(QueueHealthService $queue): View
    {
        return view('livewire.admin.queue-health', [
            'pending' => $queue->pending(),
            'oldestSeconds' => $queue->oldestPendingSeconds(),
            'failed' => $queue->failed(),
            'failedCount' => $queue->failedCount(),
        ]);
    }

    /**
     * Re-checked on every action, not once on mount.
     *
     * A Livewire component mounts once and then serves many requests against
     * the same instance. A role changed mid-session must take effect on the
     * next action, not on the next full page load — and `retry` re-runs
     * queued work, which is not something to leave to a stale check.
     */
    private function authoriseOperator(): void
    {
        /** @var User|null $actor */
        $actor = Auth::user();

        abort_unless($actor?->isSuperAdmin() === true, 403);
    }
}
