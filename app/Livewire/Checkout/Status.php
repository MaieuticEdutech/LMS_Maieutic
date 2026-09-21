<?php

declare(strict_types=1);

namespace App\Livewire\Checkout;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Payment received — activating access" (architecture.md §11.2 step 9).
 *
 * POLLS FOR THE WEBHOOK'S RESULT. IT DOES NOT PRODUCE ONE. Razorpay's
 * client-side callback that lands a buyer here is informational only
 * (architecture.md §11.2, "client callback (INFORMATIONAL ONLY)") — this
 * component's only job is to keep re-reading `orders.status` until the
 * authoritative webhook path
 * ({@see \App\Http\Controllers\Webhooks\ProcessRazorpayWebhookController} ->
 * {@see \App\Jobs\Payment\ProcessPaymentWebhook} ->
 * {@see \App\Actions\Payment\SettleCapturedPayment}) has actually settled
 * the order. A buyer who closes this tab the instant Razorpay's modal closes
 * still gets enrolled — this page is a convenience, not a dependency of the
 * purchase.
 *
 * Reachable by order number alone, no auth required — the same "possession
 * of an unguessable token is the authorisation" trade routes/web.php's
 * certificate verification uses, since the buyer may not have an account yet
 * (`order_number` is `ORD-` plus 10 random alphanumeric characters, per
 * OrderFactory — not sequential, not guessable from another order's URL).
 */
#[Layout('layouts.public')]
final class Status extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order->loadMissing('course');
    }

    /**
     * Re-read fresh on every poll — the view decides, from the enum, whether
     * to keep polling at all.
     */
    public function render(): View
    {
        $this->order->refresh()->loadMissing('course');

        return view('livewire.checkout.status', [
            'stillWaiting' => in_array($this->order->status, [OrderStatus::Created, OrderStatus::Pending], true),
            'viewerIsBuyer' => auth()->check() && auth()->id() === $this->order->user_id,
        ]);
    }
}
