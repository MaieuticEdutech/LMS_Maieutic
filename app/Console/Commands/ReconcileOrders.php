<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payment\RecordFailedPayment;
use App\Actions\Payment\SettleCapturedPayment;
use App\Contracts\Payment\PaymentGateway;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;
use Throwable;

/**
 * The self-healing path architecture.md §11.3 rule 8 requires: "Missed
 * webhooks are self-healing... queries the gateway for `pending` orders
 * older than N minutes and settles them through the SAME action."
 *
 * WHY A PENDING ORDER CAN OUTLIVE ITS WEBHOOK:
 * Razorpay's webhook delivery is best-effort. An outage on either side, a
 * dropped connection, or a misconfigured endpoint can mean a payment that
 * genuinely succeeded never reaches
 * {@see \App\Http\Controllers\Webhooks\ProcessRazorpayWebhookController}.
 * Without this command, that student paid and received nothing, and nobody
 * would know until they complained.
 *
 * SAME SETTLEMENT ACTION AS THE WEBHOOK, DELIBERATELY. This command asks the
 * gateway for the order's own payments and hands anything captured to
 * {@see SettleCapturedPayment} — the identical Action the webhook job calls.
 * A payment settled by reconciliation and one settled by webhook are
 * indistinguishable in every table this application writes to.
 */
final class ReconcileOrders extends Command
{
    protected $signature = 'lms:orders:reconcile {--minutes=15 : Only consider orders pending at least this long}';

    protected $description = 'Settle pending orders whose payment webhook never arrived, by asking the gateway directly';

    public function handle(
        PaymentGateway $gateway,
        SettleCapturedPayment $settleCapturedPayment,
        RecordFailedPayment $recordFailedPayment,
    ): int {
        $minutes = (int) $this->option('minutes');

        $stale = Order::query()
            ->where('status', OrderStatus::Pending)
            ->whereNotNull('gateway_order_id')
            ->where('placed_at', '<=', now()->subMinutes($minutes))
            ->get();

        if ($stale->isEmpty()) {
            $this->info('No stale pending orders to reconcile.');

            return self::SUCCESS;
        }

        $settled = 0;
        $failed = 0;
        $stillPending = 0;

        foreach ($stale as $order) {
            try {
                $payments = $gateway->fetchOrderPayments((string) $order->gateway_order_id);
            } catch (Throwable $e) {
                $this->warn("Order {$order->order_number}: gateway lookup failed — {$e->getMessage()}");

                continue;
            }

            $captured = array_values(array_filter(
                $payments,
                static fn ($payment): bool => $payment->status === 'captured',
            ));

            if ($captured !== []) {
                $settleCapturedPayment->handle($captured[0]);
                $this->line("Order {$order->order_number}: settled from a payment the webhook missed.");
                $settled++;

                continue;
            }

            $failedPayments = array_values(array_filter(
                $payments,
                static fn ($payment): bool => $payment->status === 'failed',
            ));

            if ($failedPayments !== []) {
                $recordFailedPayment->handle($failedPayments[0]);
                $this->line("Order {$order->order_number}: recorded as failed from a delivery the webhook missed.");
                $failed++;

                continue;
            }

            // Genuinely still awaiting payment — nothing to reconcile yet.
            // lms:orders:cancel-abandoned, not this command, is what
            // eventually retires an order stuck here indefinitely.
            $stillPending++;
        }

        $this->info(sprintf(
            'Reconciled %d order(s): %d settled, %d failed, %d still pending.',
            $stale->count(),
            $settled,
            $failed,
            $stillPending,
        ));

        return self::SUCCESS;
    }
}
