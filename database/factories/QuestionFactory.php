<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    /**
     * Default state: a SINGLE-CHOICE question worth 1 mark, no negative
     * marking.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'type' => QuestionType::SingleChoice,
            'body' => fake()->sentence().'?',
            'explanation' => fake()->optional()->sentence(),
            'marks' => 1,
            'negative_marks' => 0,
            'position' => 0,
            'meta' => null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Type states
    |--------------------------------------------------------------------------
    */

    public function multipleChoice(): static
    {
        return $this->state(fn (): array => ['type' => QuestionType::MultipleChoice]);
    }

    public function trueFalse(): static
    {
        return $this->state(fn (): array => ['type' => QuestionType::TrueFalse]);
    }

    /**
     * Short answer: graded from accepted answers in `meta`, not options.
     */
    public function shortAnswer(): static
    {
        return $this->state(fn (): array => [
            'type' => QuestionType::ShortAnswer,
            'meta' => ['accepted_answers' => [fake()->word()]],
        ]);
    }
}
