<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment attempt against an {@see Order} (architecture.md §6.4, §11).
 * Separate from `Order` because one order legitimately has several payment
 * attempts.
 *
 * @property int $id
 * @property int $order_id
 * @property string $gateway
 * @property string $gateway_payment_id
 * @property string|null $method
 * @property int $amount
 * @property string $currency
 * @property PaymentStatus $status
 * @property CarbonImmutable|null $captured_at
 * @property int $refunded_amount
 * @property string|null $failure_code
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $raw_payload
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * `order_id`, `status`, `amount` and `refunded_amount` are DELIBERATELY
     * ABSENT (NFR-SEC-07) — `order_id` follows the owning-relation
     * convention already used throughout this track
     * (`$order->payments()->create([...])`); `status`/`amount`/
     * `refunded_amount` are financial state, set only from a verified
     * gateway response, never from mass-assigned request input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'gateway',
        'gateway_payment_id',
        'method',
        'currency',
        'captured_at',
        'failure_code',
        'failure_reason',
        'raw_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'refunded_amount' => 'integer',
            'status' => PaymentStatus::class,
            'captured_at' => 'immutable_datetime',
            'raw_payload' => 'array',
        ];
    }

    /**
     * The captured amount as a Money value object.
     *
     * Named `money`, not `amount`, so it does not collide with the raw
     * `amount` column's own `integer` cast above — same separation
     * `Order::price()`-style accessors use from their underlying columns.
     *
     * @return Attribute<Money, never>
     */
    protected function money(): Attribute
    {
        return Attribute::make(
            get: fn (): Money => Money::fromMinor($this->amount, $this->currency),
        );
    }

    /**
     * @return Attribute<Money, never>
     */
    protected function refundMoney(): Attribute
    {
        return Attribute::make(
            get: fn (): Money => Money::fromMinor($this->refunded_amount, $this->currency),
        );
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
