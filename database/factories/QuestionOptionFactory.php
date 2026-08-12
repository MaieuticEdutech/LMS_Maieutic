<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionOption>
 */
class QuestionOptionFactory extends Factory
{
    protected $model = QuestionOption::class;

    /**
     * Default state: an INCORRECT option. Correct options are the exception
     * a test should ask for explicitly (`correct()`), matching FR-ASMT-07 —
     * most options on a question are distractors, not the answer.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'body' => fake()->words(3, true),
            'is_correct' => false,
            'position' => 0,
        ];
    }

    public function correct(): static
    {
        return $this->state(fn (): array => ['is_correct' => true]);
    }
}
