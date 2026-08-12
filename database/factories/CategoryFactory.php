<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // word() returns a string; words(2, true) is typed array|string by the
        // faker stubs, which makes every downstream string operation unsafe.
        $name = fake()->unique()->word();

        return [
            'parent_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::random(5),
            'description' => fake()->sentence(),
            'position' => 0,
        ];
    }

    public function childOf(Category $parent): static
    {
        return $this->state(fn (): array => ['parent_id' => $parent->getKey()]);
    }
}
