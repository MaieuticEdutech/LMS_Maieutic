<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The single record of "who has access to what" (architecture.md §6.4,
 * §12.2). This model is storage only — it implements no access or grant
 * logic. `EnrollmentAccessService::grantsAccess()` and `GrantEnrollment` are
 * Govind's single-owner components (planning.md §21.3) and are the only
 * code that may create an enrollment or decide what "has access" means.
 *
 * @property int $id
 * @property int $user_id
 * @property int $course_id
 * @property int|null $order_id
 * @property EnrollmentSource $source
 * @property EnrollmentStatus $status
 * @property CarbonImmutable $enrolled_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $completed_at
 * @property int|null $granted_by
 * @property int|null $revoked_by
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revoke_reason
 * @property int $progress_percentage
 * @property int $completed_lessons_count
 * @property int|null $last_lesson_id
 * @property CarbonImmutable|null $last_accessed_at
 */
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    /**
     * DELIBERATELY MINIMAL (NFR-SEC-07). Excluded, same reasoning as every
     * other model in this codebase excludes its escalation-risk fields:
     *
     *   - `user_id`, `course_id` — identity of the grant itself; redirecting
     *     an enrollment to a different student or course is not something
     *     any mass-assignment should be able to do (owning-relation
     *     convention used throughout this track).
     *   - `status`, `source` — the access-relevant state. Set only by
     *     `GrantEnrollment` (Phase 6), based on which authorised path
     *     invoked it, never from request input.
     *   - `granted_by`, `revoked_by` — audit trail, set explicitly to the
     *     authenticated actor, the same convention as `assessments.created_by`.
     *   - `progress_percentage`, `completed_lessons_count` — rebuildable
     *     caches (`lms:progress:rebuild`), not truth, same convention as
     *     `Course`'s counter caches.
     *
     * `GrantEnrollment` and `lms:progress:rebuild` are free to set any of
     * these explicitly by name or via `forceFill()` — they are already-
     * authorised, gated code, which is exactly what mass-assignment
     * protection exists to require.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'enrolled_at',
        'expires_at',
        'completed_at',
        'revoked_at',
        'revoke_reason',
        'last_lesson_id',
        'last_accessed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => EnrollmentSource::class,
            'status' => EnrollmentStatus::class,
            'enrolled_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'progress_percentage' => 'integer',
            'completed_lessons_count' => 'integer',
            'last_accessed_at' => 'immutable_datetime',
        ];
    }

    /**
     * A simple status filter — NOT the access gate. Whether an enrollment
     * actually grants access (status ∈ {active, completed} AND not expired)
     * is `EnrollmentAccessService::grantsAccess()`'s question to answer, not
     * this scope's (architecture.md §12.2). Never substitute this for that
     * service (rule S-8).
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', EnrollmentStatus::Active);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lastLesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'last_lesson_id');
    }

    /**
     * @return HasMany<LessonProgress, $this>
     */
    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }
}
