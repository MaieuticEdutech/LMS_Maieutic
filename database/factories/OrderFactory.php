<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Default state: a freshly CREATED order for a known, resolved buyer —
     * no gateway order yet, nothing charged.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomElement([99900, 149900, 249900, 499900]);

        return [
            'order_number' => 'ORD-'.strtoupper(Str::random(10)),
            'user_id' => User::factory()->student(),
            'course_id' => Course::factory(),
            'buyer_name' => fake()->name(),
            'buyer_email' => fake()->unique()->safeEmail(),
            'buyer_phone' => fake()->numerify('##########'),
            'amount_subtotal' => $subtotal,
            'discount_amount' => 0,
            'amount_total' => $subtotal,
            'currency' => 'INR',
            'status' => OrderStatus::Created,
            'gateway' => 'razorpay',
            'gateway_order_id' => null,
            'placed_at' => null,
            'paid_at' => null,
            'failed_reason' => null,
            'meta' => null,
        ];
    }

    /**
     * A buyer with no account yet — the purchase-before-account-exists path
     * (architecture.md §6.4's nullable-`user_id` mechanism).
     */
    public function forGuestBuyer(): static
    {
        return $this->state(fn (): array => ['user_id' => null]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Pending,
            'gateway_order_id' => 'order_'.Str::random(14),
            'placed_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Paid,
            'gateway_order_id' => $attributes['gateway_order_id'] ?? 'order_'.Str::random(14),
            'placed_at' => $attributes['placed_at'] ?? now(),
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Failed,
            'failed_reason' => fake()->sentence(),
        ]);
    }

    public function discounted(int $discountAmount): static
    {
        return $this->state(fn (array $attributes): array => [
            'discount_amount' => $discountAmount,
            'amount_total' => max(0, $attributes['amount_subtotal'] - $discountAmount),
        ]);
    }
}
