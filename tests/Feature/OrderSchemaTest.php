<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Phase 3 (Track C) — orders schema and model invariants
|--------------------------------------------------------------------------
|
| Guards the database-level protections the application relies on but never
| re-checks at runtime (architecture.md §6.4, §6.5, ADR-007, ADR-012).
|
*/

it('creates the orders table', function (): void {
    expect(Schema::hasTable('orders'))->toBeTrue();
});

/*
| CHECK CONSTRAINT (ADR-012).
*/
it('rejects an invalid status at the database level', function (): void {
    $order = Order::factory()->create();

    expect(fn () => DB::table('orders')->where('id', $order->id)->update(['status' => 'disputed']))
        ->toThrow(QueryException::class);
});

it('accepts every order status the application can produce', function (OrderStatus $status): void {
    $order = Order::factory()->create();
    $order->forceFill(['status' => $status])->save();

    expect($order->refresh()->status)->toBe($status);
})->with('order statuses');

/*
| MONEY IS NEVER NEGATIVE (ADR-007).
*/
it('rejects a negative amount at the database level', function (string $column): void {
    $order = Order::factory()->create();

    expect(fn () => DB::table('orders')->where('id', $order->id)->update([$column => -100]))
        ->toThrow(QueryException::class);
})->with([
    'amount_subtotal', 'discount_amount', 'amount_total',
]);

it('allows a fully discounted order to total zero', function (): void {
    $order = Order::factory()->create(['amount_subtotal' => 50000, 'discount_amount' => 50000, 'amount_total' => 0]);

    expect($order->refresh()->amount_total)->toBe(0);
});

/*
| UNIQUE CONSTRAINTS.
*/
it('enforces a unique order_number', function (): void {
    Order::factory()->create(['order_number' => 'ORD-DUPLICATE']);

    expect(fn () => Order::factory()->create(['order_number' => 'ORD-DUPLICATE']))
        ->toThrow(QueryException::class);
});

it('enforces a unique gateway_order_id but allows multiple null values', function (): void {
    Order::factory()->create(['gateway_order_id' => null]);
    Order::factory()->create(['gateway_order_id' => null]);

    Order::factory()->create(['gateway_order_id' => 'order_dup']);

    expect(fn () => Order::factory()->create(['gateway_order_id' => 'order_dup']))
        ->toThrow(QueryException::class);
});

/*
| USER DELETION MUST NOT DELETE ORDERS (Phase 3 DoD, NFR-DATA-05).
*/
it('keeps an order when its buyer is deleted', function (): void {
    $buyer = User::factory()->student()->create();
    $order = Order::factory()->create(['user_id' => $buyer->id]);

    $buyer->forceDelete();

    $survivor = Order::query()->find($order->id);

    expect($survivor)->not->toBeNull()
        ->and($survivor?->user_id)->toBeNull()
        // The order still knows who bought it, independent of the user row.
        ->and($survivor?->buyer_name)->not->toBeNull()
        ->and($survivor?->buyer_email)->not->toBeNull();
});

it('accepts an order with no buyer account at all', function (): void {
    $order = Order::factory()->forGuestBuyer()->create();

    expect($order->refresh()->user_id)->toBeNull();
});

/*
| COURSE DELETION IS RESTRICTED WHEN ORDERS EXIST — course_id is not
| nullable, so there is nothing to null it to.
*/
it('refuses to delete a course that has orders', function (): void {
    $course = Course::factory()->create();
    Order::factory()->create(['course_id' => $course->id]);

    expect(fn () => $course->forceDelete())->toThrow(QueryException::class);
});

/*
| OWNERSHIP AND FINANCIAL FIELDS — never mass-assignable (NFR-SEC-07).
*/
it('refuses to mass-assign user_id, status or any money column', function (array $payload): void {
    expect(fn () => Order::factory()->make()->fill($payload))
        ->toThrow(Exception::class);
})->with([
    'user_id' => [['user_id' => 1]],
    'status' => [['status' => OrderStatus::Paid]],
    'amount_subtotal' => [['amount_subtotal' => 1]],
    'discount_amount' => [['discount_amount' => 1]],
    'amount_total' => [['amount_total' => 1]],
]);

/*
| NORMALISATION (FR-AUTH-10 — the same email must resolve to the same buyer).
*/
it('normalises buyer_email on every write path', function (): void {
    $order = Order::factory()->create(['buyer_email' => '  MiXeD@Example.COM ']);

    expect($order->refresh()->buyer_email)->toBe('mixed@example.com');
});

/*
| MONEY VALUE OBJECT ACCESSORS.
*/
it('exposes subtotal, discount and total as Money value objects', function (): void {
    $order = Order::factory()->create([
        'amount_subtotal' => 150000,
        'discount_amount' => 5000,
        'amount_total' => 145000,
        'currency' => 'INR',
    ]);

    expect($order->subtotal->amount)->toBe(150000)
        ->and($order->discount->amount)->toBe(5000)
        ->and($order->total->amount)->toBe(145000)
        ->and($order->total->currency)->toBe('INR');
});

dataset('order statuses', fn (): array => OrderStatus::cases());
