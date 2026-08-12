<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A section of a course, containing lessons (FR-CNT-01).
 *
 * @property int $id
 * @property int $course_id
 * @property string $title
 * @property string|null $description
 * @property int $position
 * @property bool $is_published
 * @property int $lessons_count
 */
class Module extends Model
{
    /** @use HasFactory<\Database\Factories\ModuleFactory> */
    use HasFactory;

    /**
     * `course_id` is absent: a module never moves between courses. Creating
     * one under a course is an Action, not a mass-assign.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'position',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_published' => 'boolean',
            'lessons_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }

    /**
     * Only lessons a student may see (FR-CNT-05).
     *
     * @return HasMany<Lesson, $this>
     */
    public function publishedLessons(): HasMany
    {
        return $this->lessons()->where('is_published', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position');
    }
}
