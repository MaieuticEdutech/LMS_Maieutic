<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Default state: a freshly CREATED payment attempt, not yet authorized
     * or captured.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'gateway' => 'razorpay',
            'gateway_payment_id' => 'pay_'.Str::random(14),
            'method' => null,
            'amount' => fake()->randomElement([99900, 149900, 249900, 499900]),
            'currency' => 'INR',
            'status' => PaymentStatus::Created,
            'captured_at' => null,
            'refunded_amount' => 0,
            'failure_code' => null,
            'failure_reason' => null,
            'raw_payload' => null,
        ];
    }

    public function captured(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Captured,
            'method' => fake()->randomElement(['card', 'upi', 'netbanking']),
            'captured_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Failed,
            'failure_code' => 'BAD_REQUEST_ERROR',
            'failure_reason' => fake()->sentence(),
        ]);
    }

    public function refunded(int $refundedAmount): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Refunded,
            'refunded_amount' => min($refundedAmount, $attributes['amount']),
        ]);
    }
}
