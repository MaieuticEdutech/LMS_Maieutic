<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\MediaPurpose;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A sellable course (FR-CRS-01…11).
 *
 * @property int $id
 * @property int|null $category_id
 * @property string $title
 * @property string $slug
 * @property string|null $subtitle
 * @property string|null $description
 * @property list<string>|null $outcomes
 * @property list<string>|null $requirements
 * @property CourseLevel $level
 * @property string $language
 * @property string|null $thumbnail_path
 * @property int $price_amount
 * @property string $currency
 * @property CourseStatus $status
 * @property CarbonImmutable|null $published_at
 * @property bool $requires_final_test
 * @property int|null $created_by
 * @property int $modules_count
 * @property int $lessons_count
 * @property int $total_duration_seconds
 */
class Course extends Model
{
    /** @use HasFactory<\Database\Factories\CourseFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * `status`, `price_amount` and `created_by` are DELIBERATELY ABSENT
     * (NFR-SEC-07, planning.md §9 rule 3).
     *
     * Publishing is a guarded transition with its own validation
     * (CoursePublishValidator, Phase 5), and price is money. Neither may be
     * changed by a mass-assign from request input — they are set explicitly,
     * by name, inside an Action that has already authorised the change.
     *
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'subtitle',
        'description',
        'outcomes',
        'requirements',
        'level',
        'language',
        'thumbnail_path',
        'currency',
        'requires_final_test',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcomes' => 'array',
            'requirements' => 'array',
            'level' => CourseLevel::class,
            'status' => CourseStatus::class,
            'published_at' => 'immutable_datetime',
            'requires_final_test' => 'boolean',
            'price_amount' => 'integer',
            'modules_count' => 'integer',
            'lessons_count' => 'integer',
            'total_duration_seconds' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The price as a Money value object (ADR-007).
     *
     * Read-only by design. Setting a price goes through an Action, because
     * changing a price must never alter the recorded amount of an existing
     * order (FR-CRS-11) and that is a rule an attribute setter cannot express.
     *
     * @return Attribute<Money, never>
     */
    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn (): Money => Money::fromMinor($this->price_amount, $this->currency),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @return HasMany<Module, $this>
     */
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('position');
    }

    /**
     * Every lesson in the course, across all modules.
     *
     * @return HasManyThrough<Lesson, Module, $this>
     */
    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    /**
     * Assigned instructors — THE BASIS OF INSTRUCTOR AUTHORISATION (§8.4).
     *
     * @return BelongsToMany<User, $this>
     */
    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_instructor')
            ->withPivot(['role_in_course', 'assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    /**
     * Students who hold, or have ever held, access to this course.
     *
     * NOT scoped by status, deliberately. This answers "has anyone ever been
     * enrolled here", which is the question FR-CRS-06 asks before allowing a
     * delete — a refunded or expired enrollment is still a commercial record
     * that must survive.
     *
     * Never use this to decide access. That is
     * `EnrollmentAccessService::grantsAccess()` and only that (rule S-8).
     *
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return MorphMany<MediaFile, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany(MediaFile::class, 'attachable')->orderBy('position');
    }

    /**
     * @return MorphMany<MediaFile, $this>
     */
    public function thumbnail(): MorphMany
    {
        return $this->media()->where('purpose', MediaPurpose::Thumbnail);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Courses visible in the public catalogue (FR-CRS-03).
     *
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', CourseStatus::Published);
    }

    /**
     * COURSES THIS INSTRUCTOR IS ASSIGNED TO — the single entry point for
     * every instructor-scoped query in the system (§8.4, FR-RBAC-04, AC-03).
     *
     * Phase 10 begins EVERY query here rather than at Course::query(), which
     * is what makes scope leakage require actively bypassing the standard
     * entry point rather than merely forgetting a WHERE clause. If you are
     * writing an instructor feature and not starting from this scope, stop
     * and reconsider.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAssignedTo(Builder $query, User $user): void
    {
        $query->whereHas(
            'instructors',
            static fn (Builder $instructors) => $instructors->whereKey($user->getKey()),
        );
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeInCategory(Builder $query, Category $category): void
    {
        $query->where('category_id', $category->getKey());
    }

    /*
    |--------------------------------------------------------------------------
    | Derived state
    |--------------------------------------------------------------------------
    */

    public function isPublished(): bool
    {
        return $this->status === CourseStatus::Published;
    }

    /**
     * Is this instructor assigned to this course?
     *
     * The helper behind CoursePolicy's instructor branch. Kept here so the
     * question has one implementation rather than one per policy.
     */
    public function isAssignedTo(User $user): bool
    {
        return $this->instructors()->whereKey($user->getKey())->exists();
    }
}
