<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AssessmentAttempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttemptAnswer>
 */
class AttemptAnswerFactory extends Factory
{
    protected $model = AttemptAnswer::class;

    /**
     * Default state: a single-choice answer already saved, not yet graded.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attempt_id' => AssessmentAttempt::factory(),
            'question_id' => Question::factory(),
            'selected_option_ids' => [fake()->numberBetween(1, 1000)],
            'answer_text' => null,
            'is_correct' => null,
            'marks_awarded' => null,
            'answered_at' => now(),
        ];
    }

    /**
     * A short-answer response: text instead of selected options.
     */
    public function shortAnswer(): static
    {
        return $this->state(fn (): array => [
            'selected_option_ids' => null,
            'answer_text' => fake()->word(),
        ]);
    }

    public function graded(bool $correct, float $marksAwarded = 0): static
    {
        return $this->state(fn (): array => [
            'is_correct' => $correct,
            'marks_awarded' => $marksAwarded,
        ]);
    }
}
