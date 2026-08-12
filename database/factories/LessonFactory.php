<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LessonType;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    /**
     * Default: an unpublished TEXT lesson.
     *
     * Text because it is the only type that needs no attached media, so the
     * default factory produces a complete, valid lesson without also creating
     * a MediaFile. Tests that need media say so.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->sentence(4), '.');

        return [
            'module_id' => Module::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(5),
            'type' => LessonType::Text,
            'summary' => fake()->sentence(10),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'position' => 0,
            'duration_seconds' => fake()->numberBetween(60, 1800),
            'is_published' => false,
            'meta' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['is_published' => true]);
    }

    public function ofType(LessonType $type): static
    {
        return $this->state(fn (): array => [
            'type' => $type,
            // Only text lessons carry an HTML body.
            'body' => $type === LessonType::Text ? '<p>'.fake()->paragraph().'</p>' : null,
        ]);
    }

    public function video(): static
    {
        return $this->ofType(LessonType::Video);
    }

    public function document(): static
    {
        return $this->ofType(LessonType::Document);
    }

    public function quiz(): static
    {
        return $this->ofType(LessonType::Quiz);
    }

    public function forModule(Module $module): static
    {
        return $this->state(fn (): array => ['module_id' => $module->getKey()]);
    }

    public function atPosition(int $position): static
    {
        return $this->state(fn (): array => ['position' => $position]);
    }
}
