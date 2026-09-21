<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retires orders nobody ever finished paying (architecture.md §11.4's
 * `pending -> cancelled: abandoned (scheduled sweep)`).
 *
 * SAFE TO RUN ALONGSIDE lms:orders:reconcile: the threshold here (default 24
 * hours) is deliberately far past reconciliation's own window (default 15
 * minutes), so an order this command touches has already had many
 * reconciliation sweeps to catch a payment the webhook missed. A `pending`
 * order this old with no captured or failed payment at the gateway is not a
 * timing gap — it is a checkout the buyer walked away from.
 *
 * `created` orders (checkout started, gateway order never even confirmed)
 * are swept on the same schedule for the same reason: an order stuck in
 * `created` past this window failed before a gateway order existed at all,
 * so there is nothing for reconciliation to find either.
 *
 * DOES NOT TOUCH `payments`, an enrollment, or anything financial — this is
 * housekeeping on an order that never became one. If a captured payment
 * exists for it, reconciliation would have already moved it to `paid` long
 * before this threshold, so cancelling it here can never contradict a real
 * transaction.
 */
final class CancelAbandonedOrders extends Command
{
    protected $signature = 'lms:orders:cancel-abandoned {--hours=24 : Only cancel orders started at least this long ago}';

    protected $description = 'Cancel checkout orders that were started and never completed';

    public function handle(AuditLogger $audit): int
    {
        $hours = (int) $this->option('hours');

        $abandoned = Order::query()
            ->whereIn('status', [OrderStatus::Created, OrderStatus::Pending])
            ->where('placed_at', '<=', now()->subHours($hours))
            ->get();

        if ($abandoned->isEmpty()) {
            $this->info('No abandoned orders to cancel.');

            return self::SUCCESS;
        }

        foreach ($abandoned as $order) {
            DB::transaction(static function () use ($order): void {
                $order->forceFill([
                    'status' => OrderStatus::Cancelled,
                    'failed_reason' => 'Abandoned — no payment completed within the retention window.',
                ])->save();
            });

            $audit->record(
                action: 'order.cancelled_abandoned',
                subject: $order,
                description: "Order {$order->order_number} cancelled — no payment completed.",
            );
        }

        $this->info(sprintf('Cancelled %d abandoned order(s).', $abandoned->count()));

        return self::SUCCESS;
    }
}
