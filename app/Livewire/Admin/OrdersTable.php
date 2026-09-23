<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\OrderStatus;
use App\Livewire\Concerns\WithAdminTable;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every purchase attempt (architecture.md §11, phases.md Phase 12 — "Admin
 * order/payment management and webhook event log").
 *
 * Read-mostly, same posture as EmailLogTable/AuditLogTable: orders are never
 * created or updated through a policy-gated user action — the checkout flow
 * and webhook processing write them directly (OrderPolicy's own docblock) —
 * so this screen only ever needs `viewAny`.
 *
 * OrderPolicy already refuses an instructor unconditionally (FR-INS-10, "an
 * instructor never sees a financial figure anywhere in this system"); the
 * route itself sits behind `role:super_admin` in routes/admin.php, so no
 * instructor reaches this component at all. The mount() check is defence in
 * depth, not the only gate.
 */
#[Layout('layouts.admin', [
    'breadcrumbs' => [
        ['label' => 'Administration', 'url' => '/admin'],
        ['label' => 'Orders', 'url' => null],
    ],
])]
final class OrdersTable extends Component
{
    use WithAdminTable;
    use WithPagination;

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Order::class);
    }

    /**
     * @return list<string>
     */
    protected function filterProperties(): array
    {
        return ['statusFilter'];
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    public function rows(): LengthAwarePaginator
    {
        $query = Order::query()->with('course:id,title');

        $query = $this->applySort(
            $this->applySearch($query, ['order_number', 'buyer_name', 'buyer_email']),
            default: 'placed_at',
            defaultDirection: 'desc',
        );

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        return $query->paginate($this->perPage);
    }

    /**
     * @return list<OrderStatus>
     */
    public function statusOptions(): array
    {
        return OrderStatus::cases();
    }

    public function render(): View
    {
        return view('livewire.admin.orders-table', [
            'orders' => $this->rows(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }
}
