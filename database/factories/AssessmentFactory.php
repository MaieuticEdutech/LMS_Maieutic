<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AnswerRevealPolicy;
use App\Enums\AssessmentType;
use App\Enums\ScoringPolicy;
use App\Models\Assessment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    /**
     * Default state: a PUBLISHED QUIZ attached to a lesson.
     *
     * `assessable_type`/`assessable_id` are plain placeholder values, not a
     * real relation — Track A's Lesson/Module/Course models do not exist yet
     * (track brief: assessments has no FK by design). Tests that need a real
     * attach point override these explicitly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessable_type' => 'App\\Models\\Lesson',
            'assessable_id' => fake()->numberBetween(1, 1000),
            'type' => AssessmentType::Quiz,
            'title' => fake()->sentence(4),
            'instructions' => fake()->paragraph(),
            'passing_percentage' => 70,
            'time_limit_minutes' => null,
            'max_attempts' => null,
            'scoring_policy' => ScoringPolicy::Highest,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'answer_reveal' => AnswerRevealPolicy::AfterSubmit,
            'negative_marking_enabled' => false,
            'total_marks' => 0,
            'questions_count' => 0,
            'is_published' => true,
            'available_from' => null,
            'available_until' => null,
            'created_by' => User::factory()->instructor(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Attach-point states
    |--------------------------------------------------------------------------
    */

    public function forCourse(): static
    {
        return $this->state(fn (): array => [
            'assessable_type' => 'App\\Models\\Course',
            'type' => AssessmentType::Test,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Lifecycle states
    |--------------------------------------------------------------------------
    */

    public function unpublished(): static
    {
        return $this->state(fn (): array => ['is_published' => false]);
    }

    public function timed(int $minutes = 30): static
    {
        return $this->state(fn (): array => ['time_limit_minutes' => $minutes]);
    }
}
