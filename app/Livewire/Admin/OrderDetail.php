<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * One order and every payment attempt against it (architecture.md §11,
 * §6.4's "one order legitimately has several payment attempts — fail, retry,
 * capture").
 *
 * Read-only, same reasoning as OrdersTable's own docblock: this screen exists
 * to answer "what happened to this purchase," not to let anyone change it.
 * There is no edit action anywhere on this page — an order's fields are set
 * exclusively by InitiateCheckout and SettleCapturedPayment/
 * RecordFailedPayment, never by an admin form (NFR-SEC-07-adjacent: money
 * state is never mass-assignable, and that extends to "never assignable at
 * all from this screen").
 */
#[Layout('layouts.admin', [
    'breadcrumbs' => [
        ['label' => 'Administration', 'url' => '/admin'],
        ['label' => 'Orders', 'url' => '/admin/orders'],
        ['label' => 'Detail', 'url' => null],
    ],
])]
final class OrderDetail extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        $this->authorize('view', $order);

        $this->order = $order;
    }

    public function render(): View
    {
        // Explicit eager load in render(), not only in mount(): Livewire
        // rehydrates $order from scratch on every request, and render() runs
        // on every one of those — same reasoning as InstructorDetail's own
        // docblock. preventLazyLoading throws on a lazy relation access
        // outside production, and this view reads course, user and payments.
        $this->order->loadMissing(['course:id,title,slug', 'user:id,name,email', 'payments']);

        return view('livewire.admin.order-detail');
    }
}
