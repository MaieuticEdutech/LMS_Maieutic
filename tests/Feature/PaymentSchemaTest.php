<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Phase 3 (Track C) — payments schema and model invariants
|--------------------------------------------------------------------------
|
| Guards the database-level protections the application relies on but never
| re-checks at runtime (architecture.md §6.4, §6.5, ADR-007, ADR-012).
|
*/

it('creates the payments table', function (): void {
    expect(Schema::hasTable('payments'))->toBeTrue();
});

/*
| IDEMPOTENCY KEY (architecture.md §6.5, Phase 3 DoD — "proven by a test that
| expects the insert to throw"). One gateway payment, one record.
*/
it('enforces a unique gateway_payment_id', function (): void {
    Payment::factory()->create(['gateway_payment_id' => 'pay_duplicate']);

    expect(fn () => Payment::factory()->create(['gateway_payment_id' => 'pay_duplicate']))
        ->toThrow(QueryException::class);
});

/*
| CHECK CONSTRAINT (ADR-012).
*/
it('rejects an invalid status at the database level', function (): void {
    $payment = Payment::factory()->create();

    expect(fn () => DB::table('payments')->where('id', $payment->id)->update(['status' => 'disputed']))
        ->toThrow(QueryException::class);
});

it('accepts every payment status the application can produce', function (PaymentStatus $status): void {
    $payment = Payment::factory()->create();
    $payment->forceFill(['status' => $status])->save();

    expect($payment->refresh()->status)->toBe($status);
})->with('payment statuses');

/*
| MONEY IS NEVER NEGATIVE (ADR-007).
*/
it('rejects a negative amount at the database level', function (string $column): void {
    $payment = Payment::factory()->create();

    expect(fn () => DB::table('payments')->where('id', $payment->id)->update([$column => -100]))
        ->toThrow(QueryException::class);
})->with(['amount', 'refunded_amount']);

/*
| ORDER DELETION IS RESTRICTED WHEN PAYMENTS EXIST — order_id is not
| nullable, so there is nothing to null it to (same reasoning as
| orders.course_id).
*/
it('refuses to delete an order that has payments', function (): void {
    // Order has no SoftDeletes trait — delete() is already a hard delete.
    $order = Order::factory()->create();
    Payment::factory()->create(['order_id' => $order->id]);

    expect(fn () => $order->delete())->toThrow(QueryException::class);
});

/*
| OWNERSHIP AND FINANCIAL FIELDS — never mass-assignable (NFR-SEC-07), and
| order_id follows the owning-relation convention used throughout this
| track (Question::assessment_id, QuestionOption::question_id, ...).
*/
it('refuses to mass-assign order_id, status, amount or refunded_amount', function (array $payload): void {
    expect(fn () => Payment::factory()->make()->fill($payload))
        ->toThrow(Exception::class);
})->with([
    'order_id' => [['order_id' => 1]],
    'status' => [['status' => PaymentStatus::Captured]],
    'amount' => [['amount' => 1]],
    'refunded_amount' => [['refunded_amount' => 1]],
]);

/*
| RELATIONSHIP.
*/
it('lists an order\'s payment attempts through the owning relation', function (): void {
    $order = Order::factory()->create();
    $first = Payment::factory()->failed()->create(['order_id' => $order->id]);
    $second = Payment::factory()->captured()->create(['order_id' => $order->id]);

    expect($order->payments()->pluck('id')->all())->toBe([$first->id, $second->id]);
});

/*
| MONEY VALUE OBJECT ACCESSORS.
*/
it('exposes the captured and refunded amounts as Money value objects', function (): void {
    $payment = Payment::factory()->create([
        'amount' => 249900,
        'refunded_amount' => 50000,
        'currency' => 'INR',
    ]);

    expect($payment->money->amount)->toBe(249900)
        ->and($payment->money->currency)->toBe('INR')
        ->and($payment->refundMoney->amount)->toBe(50000);
});

dataset('payment statuses', fn (): array => PaymentStatus::cases());
