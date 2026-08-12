<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One purchase attempt (architecture.md §6.4, §11). `buyer_name`,
 * `buyer_email` and `buyer_phone` are captured independently of `user`
 * because a buyer may not have an account yet when the order is created,
 * and must not disappear if that account is later deleted (NFR-DATA-05).
 *
 * @property int $id
 * @property string $order_number
 * @property int|null $user_id
 * @property int $course_id
 * @property string $buyer_name
 * @property string $buyer_email
 * @property string|null $buyer_phone
 * @property int $amount_subtotal
 * @property int $discount_amount
 * @property int $amount_total
 * @property string $currency
 * @property OrderStatus $status
 * @property string $gateway
 * @property string|null $gateway_order_id
 * @property CarbonImmutable|null $placed_at
 * @property CarbonImmutable|null $paid_at
 * @property string|null $failed_reason
 * @property array<string, mixed>|null $meta
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * `user_id`, `status` and the three money columns are DELIBERATELY
     * ABSENT (NFR-SEC-07, planning.md §9 rule 3) — same convention as
     * `Course::$fillable` excluding `status`/`price_amount`. Buyer
     * resolution and status transitions are checkout/webhook concerns
     * (Phase 12, Govind's territory); money is set once, from a verified
     * source, never from mass-assigned request input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_number',
        'course_id',
        'buyer_name',
        'buyer_email',
        'buyer_phone',
        'currency',
        'gateway',
        'gateway_order_id',
        'placed_at',
        'paid_at',
        'failed_reason',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_subtotal' => 'integer',
            'discount_amount' => 'integer',
            'amount_total' => 'integer',
            'status' => OrderStatus::class,
            'placed_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'meta' => 'array',
        ];
    }

    /**
     * Normalise the buyer email on write — same reasoning as `User`'s
     * mutator (FR-AUTH-10): the same email must resolve to the same buyer.
     */
    protected function setBuyerEmailAttribute(string $value): void
    {
        $this->attributes['buyer_email'] = mb_strtolower(trim($value));
    }

    /**
     * @return Attribute<Money, never>
     */
    protected function subtotal(): Attribute
    {
        return Attribute::make(
            get: fn (): Money => Money::fromMinor($this->amount_subtotal, $this->currency),
        );
    }

    /**
     * @return Attribute<Money, never>
     */
    protected function discount(): Attribute
    {
        return Attribute::make(
            get: fn (): Money => Money::fromMinor($this->discount_amount, $this->currency),
        );
    }

    /**
     * @return Attribute<Money, never>
     */
    protected function total(): Attribute
    {
        return Attribute::make(
            get: fn (): Money => Money::fromMinor($this->amount_total, $this->currency),
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
