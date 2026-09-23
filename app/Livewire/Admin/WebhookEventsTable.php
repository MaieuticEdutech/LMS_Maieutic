<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\WebhookStatus;
use App\Livewire\Concerns\WithAdminTable;
use App\Models\WebhookEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Read-only viewer over `webhook_events` (architecture.md §13, phases.md
 * Phase 12 — "admin order/payment management and webhook event log").
 *
 * Same reason EmailLogTable exists: a delivery record nobody can look at
 * answers no question. When a payment appears not to have gone through, the
 * first thing worth knowing is whether Razorpay's webhook ever arrived at
 * all, and if it did, what this application made of it — received, settled,
 * ignored, or failed after retries. Without this screen that question ends
 * in a database console.
 *
 * Writes nothing, ever — rows arrive only through
 * ProcessRazorpayWebhookController and ProcessPaymentWebhook.
 * WebhookEventPolicy denies every mutation to everyone, including a super
 * admin.
 */
#[Layout('layouts.admin', [
    'breadcrumbs' => [
        ['label' => 'Administration', 'url' => '/admin'],
        ['label' => 'Webhook events', 'url' => null],
    ],
])]
final class WebhookEventsTable extends Component
{
    use WithAdminTable;
    use WithPagination;

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', WebhookEvent::class);
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

    public function showFailuresOnly(): void
    {
        $this->statusFilter = WebhookStatus::Failed->value;
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, WebhookEvent>
     */
    public function rows(): LengthAwarePaginator
    {
        $query = WebhookEvent::query();

        // Payload is deliberately not searchable — same reasoning as
        // EmailLogTable excluding `error`: a large JSON column turns every
        // search into a full-text scan of the biggest thing in the table,
        // for a query nobody actually types.
        $query = $this->applySort(
            $this->applySearch($query, ['event_id', 'event_type']),
            default: 'received_at',
            defaultDirection: 'desc',
        );

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        return $query->paginate($this->perPage);
    }

    public function render(): View
    {
        return view('livewire.admin.webhook-events-table', [
            'entries' => $this->rows(),
            'statuses' => WebhookStatus::cases(),
        ]);
    }
}
