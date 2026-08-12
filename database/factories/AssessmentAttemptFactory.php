<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AttemptStatus;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssessmentAttempt>
 */
class AssessmentAttemptFactory extends Factory
{
    protected $model = AssessmentAttempt::class;

    /**
     * Default state: a freshly started, untimed, IN-PROGRESS first attempt.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'assessment_id' => Assessment::factory(),
            'user_id' => User::factory()->student(),
            'enrollment_id' => Enrollment::factory(),
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
            'expires_at' => null,
            'submitted_at' => null,
            'graded_at' => null,
            'score_marks' => null,
            'max_marks' => null,
            'score_percentage' => null,
            'is_passed' => null,
            'time_spent_seconds' => 0,
            'question_order' => [1, 2, 3],
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => AttemptStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function graded(bool $passed = true): static
    {
        return $this->state(fn (): array => [
            'status' => AttemptStatus::Graded,
            'submitted_at' => now()->subMinute(),
            'graded_at' => now(),
            'score_marks' => $passed ? 8 : 3,
            'max_marks' => 10,
            'score_percentage' => $passed ? 80 : 30,
            'is_passed' => $passed,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => AttemptStatus::Expired,
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function abandoned(): static
    {
        return $this->state(fn (): array => ['status' => AttemptStatus::Abandoned]);
    }
}
