<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MediaPurpose;
use App\Models\Lesson;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaFile>
 */
class MediaFileFactory extends Factory
{
    protected $model = MediaFile::class;

    /**
     * Default: a PDF document attached to a lesson, on the PRIVATE content
     * disk.
     *
     * Private by default is deliberate — a factory whose default landed on a
     * public disk would let a test accidentally pass while proving the
     * opposite of what it claims.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ulid = (string) Str::ulid();

        return [
            'ulid' => $ulid,
            'attachable_type' => Lesson::class,
            'attachable_id' => Lesson::factory(),
            'disk' => 'content',
            // Mirrors MediaPathResolver's V1 shape. Real paths are only ever
            // produced by that resolver (rule S-2).
            'path' => "courses/1/lessons/1/document/{$ulid}.pdf",
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => fake()->numberBetween(1024, 5_000_000),
            'checksum_sha256' => hash('sha256', $ulid),
            'purpose' => MediaPurpose::Document,
            'is_downloadable' => true,
            'position' => 0,
            'uploaded_by' => User::factory()->superAdmin(),
        ];
    }

    public function video(): static
    {
        return $this->state(function (): array {
            $ulid = (string) Str::ulid();

            return [
                'ulid' => $ulid,
                'path' => "courses/1/lessons/1/video/{$ulid}.mp4",
                'original_name' => fake()->word().'.mp4',
                'mime_type' => 'video/mp4',
                'extension' => 'mp4',
                'size_bytes' => fake()->numberBetween(10_000_000, 500_000_000),
                'purpose' => MediaPurpose::Video,
                // Videos stream; they are not offered as downloads (FR-FILE-09).
                'is_downloadable' => false,
            ];
        });
    }

    public function presentation(): static
    {
        return $this->state(fn (): array => [
            'mime_type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'extension' => 'pptx',
            'purpose' => MediaPurpose::Presentation,
            'is_downloadable' => true,
        ]);
    }

    /**
     * A course thumbnail — the ONLY purpose that is public (FR-STU-04).
     */
    public function thumbnail(): static
    {
        return $this->state(fn (): array => [
            'disk' => 'public',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'purpose' => MediaPurpose::Thumbnail,
            'is_downloadable' => false,
        ]);
    }

    public function attachedTo(Model $attachable): static
    {
        return $this->state(fn (): array => [
            'attachable_type' => $attachable::class,
            'attachable_id' => $attachable->getKey(),
        ]);
    }
}
