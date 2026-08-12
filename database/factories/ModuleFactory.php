<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    protected $model = Module::class;

    /**
     * Unpublished by default, mirroring CourseFactory: a test that wants
     * student-visible content should say so explicitly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => rtrim(fake()->sentence(3), '.'),
            'description' => fake()->sentence(12),
            'position' => 0,
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['is_published' => true]);
    }

    public function forCourse(Course $course): static
    {
        return $this->state(fn (): array => ['course_id' => $course->getKey()]);
    }

    public function atPosition(int $position): static
    {
        return $this->state(fn (): array => ['position' => $position]);
    }
}
