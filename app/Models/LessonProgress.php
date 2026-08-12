<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompletionSource;
use App\Enums\ProgressStatus;
use Carbon\CarbonImmutable;
use Database\Factories\LessonProgressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The only FACT in the progress system (architecture.md §17.1) — module and
 * course progress are derived from this table at read time, or cached on
 * `enrollments.progress_percentage`, never stored independently. Written by
 * `RecordLessonProgress` (Phase 9, Govind's); this model implements no
 * completion or recalculation logic.
 *
 * @property int $id
 * @property int $enrollment_id
 * @property int $lesson_id
 * @property int $user_id
 * @property ProgressStatus $status
 * @property int $video_position_seconds
 * @property int $video_watched_seconds
 * @property int $video_duration_seconds
 * @property CompletionSource|null $completion_source
 * @property CarbonImmutable|null $first_accessed_at
 * @property CarbonImmutable|null $completed_at
 */
class LessonProgress extends Model
{
    /** @use HasFactory<LessonProgressFactory> */
    use HasFactory;

    /**
     * `enrollment_id`, `lesson_id` and `user_id` are DELIBERATELY ABSENT —
     * owning-relation convention used throughout this track. `status` and
     * `completion_source` are also absent: `status` has a monotonic guard
     * ("completed never regresses to in_progress", FR-PROG-14) enforced by
     * `RecordLessonProgress`, not something mass-assignment should be able
     * to bypass.
     *
     * @var list<string>
     */
    protected $fillable = [
        'video_position_seconds',
        'video_watched_seconds',
        'video_duration_seconds',
        'first_accessed_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProgressStatus::class,
            'video_position_seconds' => 'integer',
            'video_watched_seconds' => 'integer',
            'video_duration_seconds' => 'integer',
            'completion_source' => CompletionSource::class,
            'first_accessed_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
