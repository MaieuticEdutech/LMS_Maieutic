<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CompletionSource;
use App\Enums\ProgressStatus;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonProgress>
 */
class LessonProgressFactory extends Factory
{
    protected $model = LessonProgress::class;

    /**
     * Default state: NOT STARTED — a row that exists but records no
     * activity yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'lesson_id' => Lesson::factory(),
            'user_id' => User::factory()->student(),
            'status' => ProgressStatus::NotStarted,
            'video_position_seconds' => 0,
            'video_watched_seconds' => 0,
            'video_duration_seconds' => 0,
            'completion_source' => null,
            'first_accessed_at' => null,
            'completed_at' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => [
            'status' => ProgressStatus::InProgress,
            'video_position_seconds' => 120,
            'video_watched_seconds' => 120,
            'video_duration_seconds' => 600,
            'first_accessed_at' => now(),
        ]);
    }

    public function completed(CompletionSource $source = CompletionSource::Video): static
    {
        return $this->state(fn (): array => [
            'status' => ProgressStatus::Completed,
            'completion_source' => $source,
            'first_accessed_at' => now()->subMinutes(10),
            'completed_at' => now(),
        ]);
    }
}
