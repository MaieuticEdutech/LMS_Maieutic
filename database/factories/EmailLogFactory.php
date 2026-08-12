<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmailStatus;
use App\Models\EmailLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLog>
 */
class EmailLogFactory extends Factory
{
    protected $model = EmailLog::class;

    /**
     * Default state: a freshly QUEUED verification email, not yet picked up
     * by the mail worker.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'to_email' => fake()->safeEmail(),
            'mailable' => 'App\\Mail\\VerifyEmail',
            'subject' => 'Verify your email address',
            'status' => EmailStatus::Queued,
            'error' => null,
            'sent_at' => null,
            'context' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => EmailStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => EmailStatus::Failed,
            'error' => fake()->sentence(),
        ]);
    }
}
