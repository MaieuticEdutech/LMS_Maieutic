<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\Artisan;

// Artisan::call rather than $this->artisan()->assertExitCode() — same reason
// as ProgressRebuildTest: it returns a plain int, which the PendingCommand
// fluent API is not statically resolvable to at level 8.

it('cancels a pending order abandoned well past the retention window', function (): void {
    $order = Order::factory()->pending()->create(['placed_at' => now()->subHours(30)]);

    expect(Artisan::call('lms:orders:cancel-abandoned'))->toBe(0);

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->failed_reason)->not->toBeNull();
});

it('cancels a created order that never even reached the gateway', function (): void {
    $order = Order::factory()->create([
        'status' => OrderStatus::Created,
        'gateway_order_id' => null,
        'placed_at' => now()->subHours(30),
    ]);

    expect(Artisan::call('lms:orders:cancel-abandoned'))->toBe(0);

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
});

it('leaves a recently-started order alone', function (): void {
    $order = Order::factory()->pending()->create(['placed_at' => now()->subHours(1)]);

    expect(Artisan::call('lms:orders:cancel-abandoned'))->toBe(0);

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

it('never touches a paid order, however old', function (): void {
    $order = Order::factory()->paid()->create(['placed_at' => now()->subDays(10)]);

    expect(Artisan::call('lms:orders:cancel-abandoned'))->toBe(0);

    expect($order->refresh()->status)->toBe(OrderStatus::Paid);
});

it('respects the --hours option', function (): void {
    $order = Order::factory()->pending()->create(['placed_at' => now()->subHours(3)]);

    expect(Artisan::call('lms:orders:cancel-abandoned', ['--hours' => 1]))->toBe(0);

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
});
