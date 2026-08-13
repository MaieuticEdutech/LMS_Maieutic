<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\EmailStatus;
use App\Livewire\Concerns\WithAdminTable;
use App\Models\EmailLog;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Read-only viewer over `email_logs` (FR-MAIL-10, phases.md Phase 11 —
 * "admin: email log viewer with status and error").
 *
 * ═════════════════════════════════════════════════════════════════════════
 * WHY THIS SCREEN IS THE POINT OF THE LOG, NOT AN EXTRA.
 *
 * `email_logs` has been written since Phase 11's infrastructure landed and
 * read by nothing. A delivery record nobody can look at answers no question:
 * the whole reason to record a send is the moment a student says "I never got
 * the activation email" and somebody has to establish whether it left the
 * building. Without a viewer that question ends in a database console, or more
 * often in a guess.
 *
 * So the default sort is newest-first and the failure filter is one click:
 * the two things anyone opening this screen is actually doing.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * Writes nothing, ever. Rows arrive only through LogOutboundEmail, and
 * EmailLogPolicy denies every mutation to everyone.
 */
#[Layout('layouts.admin', [
    'breadcrumbs' => [
        ['label' => 'Administration', 'url' => '/admin'],
        ['label' => 'Email log', 'url' => null],
    ],
])]
final class EmailLogTable extends Component
{
    use WithAdminTable;
    use WithPagination;

    /**
     * Constrains the list to one delivery state. Bound to a <select> built
     * from the enum rather than from distinct values in the table — unlike
     * audit actions, the three states are fixed and known, and an empty
     * database should still offer them.
     */
    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', EmailLog::class);
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * One click to the only view that matters during an incident.
     */
    public function showFailuresOnly(): void
    {
        $this->statusFilter = EmailStatus::Failed->value;
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, EmailLog>
     */
    public function rows(): LengthAwarePaginator
    {
        $query = EmailLog::query();

        $query = $this->applySort(
            /*
             * Recipient, subject and mailable — the three things a support
             * query arrives with. `error` is deliberately NOT searchable: a
             * stack-trace fragment is not something anyone types, and
             * including it would make every search a full-text scan of the
             * largest column in the table.
             */
            $this->applySearch($query, ['to_email', 'subject', 'mailable']),
            default: 'created_at',
            defaultDirection: 'desc',
        );

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        return $query->paginate($this->perPage);
    }

    /**
     * Counts per state, for the summary row.
     *
     * One grouped query rather than three counts, and it deliberately ignores
     * the current filter: these are the totals, and a "failed" tile that
     * changed when you filtered to failures would be telling you nothing.
     *
     * @return array{queued: int, sent: int, failed: int, total: int}
     */
    public function summary(): array
    {
        /** @var array<string, int> $counts */
        $counts = EmailLog::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $queued = (int) ($counts[EmailStatus::Queued->value] ?? 0);
        $sent = (int) ($counts[EmailStatus::Sent->value] ?? 0);
        $failed = (int) ($counts[EmailStatus::Failed->value] ?? 0);

        return [
            'queued' => $queued,
            'sent' => $sent,
            'failed' => $failed,
            'total' => $queued + $sent + $failed,
        ];
    }

    public function render(): View
    {
        return view('livewire.admin.email-log-table', [
            'entries' => $this->rows(),
            'summary' => $this->summary(),
            'statuses' => EmailStatus::cases(),
        ]);
    }
}
