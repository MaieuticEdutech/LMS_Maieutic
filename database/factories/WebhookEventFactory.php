<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WebhookStatus;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    /**
     * Default state: a freshly RECEIVED Razorpay payment-captured event, not
     * yet picked up by `ProcessPaymentWebhook`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gateway' => 'razorpay',
            'event_id' => 'evt_'.fake()->unique()->regexify('[A-Za-z0-9]{14}'),
            'event_type' => 'payment.captured',
            'payload' => ['event' => 'payment.captured', 'payload' => []],
            'signature' => fake()->sha256(),
            'status' => WebhookStatus::Received,
            'attempts' => 0,
            'received_at' => now(),
            'processed_at' => null,
            'last_error' => null,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn (): array => [
            'status' => WebhookStatus::Processed,
            'attempts' => 1,
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => WebhookStatus::Failed,
            'attempts' => 5,
            'last_error' => fake()->sentence(),
        ]);
    }
}
