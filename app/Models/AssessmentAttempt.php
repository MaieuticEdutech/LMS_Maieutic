<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttemptStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AssessmentAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One student's attempt at an {@see Assessment} (architecture.md §6.4,
 * §10.2). `ulid` is the URL-exposed handle (`id` never is, per
 * architecture.md §18's IDOR mitigation). This model implements no grading
 * or lifecycle logic — that is `SubmitAttempt`/`GradingService`
 * (Phase 8).
 *
 * @property int $id
 * @property string $ulid
 * @property int $assessment_id
 * @property int $user_id
 * @property int $enrollment_id
 * @property int $attempt_number
 * @property AttemptStatus $status
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $graded_at
 * @property string|null $score_marks
 * @property string|null $max_marks
 * @property string|null $score_percentage
 * @property bool|null $is_passed
 * @property int $time_spent_seconds
 * @property list<int> $question_order
 */
class AssessmentAttempt extends Model
{
    /** @use HasFactory<AssessmentAttemptFactory> */
    use HasFactory;

    /**
     * DELIBERATELY MINIMAL (NFR-SEC-07), same reasoning as `Enrollment`:
     *
     *   - `assessment_id`, `user_id`, `enrollment_id` — identity of the
     *     attempt; owning-relation convention used throughout this track.
     *   - `status`, `attempt_number` — lifecycle state and sequencing,
     *     owned by `StartAttempt`/`SubmitAttempt` (Phase 8).
     *   - `score_marks`, `max_marks`, `score_percentage`, `is_passed` —
     *     grading results, written only by `GradingService` after server-
     *     side grading (NFR-SEC-21) — never from request input.
     *   - `question_order` — the FR-ASMT-18 snapshot, set once at attempt
     *     creation and immutable after.
     *
     * @var list<string>
     */
    protected $fillable = [
        'started_at',
        'expires_at',
        'submitted_at',
        'graded_at',
        'time_spent_seconds',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'started_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'graded_at' => 'immutable_datetime',
            'score_marks' => 'decimal:2',
            'max_marks' => 'decimal:2',
            'score_percentage' => 'decimal:2',
            'is_passed' => 'boolean',
            'time_spent_seconds' => 'integer',
            'question_order' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return HasMany<AttemptAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class, 'attempt_id');
    }
}
