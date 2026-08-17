<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseReview>
 */
final class CourseReviewFactory extends Factory
{
    protected $model = CourseReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'rating' => $this->faker->numberBetween(1, 5),
            'body' => $this->faker->optional()->paragraph(),
        ];
    }

    /**
     * NOTE: creating a review through this factory does NOT move the cached
     * counters on `courses` — SubmitCourseReview is their only writer. Tests
     * that care about the average must go through the Action, which is the
     * point: a test that could set up a rating without it would not be
     * exercising the thing that keeps the two in step.
     */
    public function rated(int $rating): self
    {
        return $this->state(fn (): array => ['rating' => $rating]);
    }

    public function withoutWords(): self
    {
        return $this->state(fn (): array => ['body' => null]);
    }
}
