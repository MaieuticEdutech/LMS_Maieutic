<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    /**
     * Default state: an ACTIVE enrollment via PURCHASE, linked to a paid
     * order — the common case a test needs.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'course_id' => Course::factory(),
            'order_id' => Order::factory()->paid(),
            'source' => EnrollmentSource::Purchase,
            'status' => EnrollmentStatus::Active,
            'enrolled_at' => now(),
            'expires_at' => null,
            'completed_at' => null,
            'granted_by' => null,
            'revoked_by' => null,
            'revoked_at' => null,
            'revoke_reason' => null,
            'progress_percentage' => 0,
            'completed_lessons_count' => 0,
            'last_lesson_id' => null,
            'last_accessed_at' => null,
        ];
    }

    /**
     * An admin-granted enrollment with no purchase behind it.
     */
    public function adminGranted(): static
    {
        return $this->state(fn (): array => [
            'order_id' => null,
            'source' => EnrollmentSource::AdminGrant,
            'granted_by' => User::factory()->superAdmin(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => EnrollmentStatus::Suspended]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => EnrollmentStatus::Completed,
            'progress_percentage' => 100,
            'completed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => EnrollmentStatus::Expired,
            'expires_at' => now()->subDay(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'status' => EnrollmentStatus::Refunded,
            'revoked_by' => User::factory()->superAdmin(),
            'revoked_at' => now(),
            'revoke_reason' => 'Refund processed.',
        ]);
    }
}
