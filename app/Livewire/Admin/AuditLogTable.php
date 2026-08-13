<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithAdminTable;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Read-only viewer over the append-only `audit_logs` table (FR-ADM-15,
 * NFR-SEC-17). This component never writes to AuditLog — entries arrive only
 * through App\Services\Audit\AuditLogger, called from Actions elsewhere in
 * the system. AuditLogPolicy already restricts viewAny/view to a super admin
 * and denies every mutation to everyone (including a super admin), so
 * mount() only needs to consult it for the read gate.
 */
#[Layout('layouts.admin', [
    'breadcrumbs' => [
        ['label' => 'Administration', 'url' => '/admin'],
        ['label' => 'Audit Log', 'url' => null],
    ],
])]
final class AuditLogTable extends Component
{
    use WithAdminTable;
    use WithPagination;

    /**
     * Constrains the list to one action string, e.g. 'auth.login.succeeded'.
     * Bound to a <select> populated from actionOptions() — the real distinct
     * values recorded so far, not a hardcoded list, since later phases keep
     * adding new action strings as more of the system gets audited.
     */
    public string $actionFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', AuditLog::class);
    }

    public function updatingActionFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function rows(): LengthAwarePaginator
    {
        $query = AuditLog::query()->with('user:id,name,email');

        $query = $this->applySort(
            $this->applySearch($query, ['action', 'description']),
            default: 'created_at',
            defaultDirection: 'desc',
        );

        if ($this->actionFilter !== '') {
            $query->where('action', $this->actionFilter);
        }

        return $query->paginate($this->perPage);
    }

    /**
     * The real distinct actions recorded so far, for the filter <select>.
     * Queried fresh rather than hardcoded (see class docblock) — this table
     * accumulates new action strings as later phases add more auditing.
     *
     * @return list<string>
     */
    public function actionOptions(): array
    {
        /** @var list<string> $actions */
        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();

        return $actions;
    }

    public function render(): View
    {
        return view('livewire.admin.audit-log-table', [
            'entries' => $this->rows(),
            'actionOptions' => $this->actionOptions(),
        ]);
    }
}
