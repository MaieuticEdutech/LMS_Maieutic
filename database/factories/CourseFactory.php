<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    /**
     * Default: a DRAFT, paid course.
     *
     * Draft by default because publishing is a guarded transition with its own
     * validation — a test that wants a published course should say so, so the
     * intent is visible at the call site.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'category_id' => null,
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title).'-'.Str::random(6),
            'subtitle' => fake()->sentence(8),
            // An explicit array literal of paragraph() calls: paragraphs() is
            // typed array|string by the faker stubs, so implode() cannot
            // safely take it.
            'description' => implode("\n\n", [
                fake()->paragraph(),
                fake()->paragraph(),
                fake()->paragraph(),
            ]),
            'outcomes' => [fake()->sentence(), fake()->sentence(), fake()->sentence()],
            'requirements' => [fake()->sentence()],
            'level' => fake()->randomElement(CourseLevel::cases()),
            'language' => 'en',
            'thumbnail_path' => null,
            // Paise. Always > 0 — the database CHECK enforces it (ADR-014).
            'price_amount' => fake()->numberBetween(49900, 999900),
            'currency' => 'INR',
            'status' => CourseStatus::Draft,
            'published_at' => null,
            'requires_final_test' => false,
            'created_by' => User::factory()->superAdmin(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => CourseStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => CourseStatus::Archived]);
    }

    public function inCategory(Category $category): static
    {
        return $this->state(fn (): array => ['category_id' => $category->getKey()]);
    }

    /**
     * @param  int  $paise  Price in the currency's minor unit.
     */
    public function pricedAt(int $paise): static
    {
        return $this->state(fn (): array => ['price_amount' => $paise]);
    }

    public function requiringFinalTest(): static
    {
        return $this->state(fn (): array => ['requires_final_test' => true]);
    }
}
