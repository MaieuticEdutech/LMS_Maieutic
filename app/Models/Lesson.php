<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LessonType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single unit of learning content (FR-CNT-02, FR-CNT-06).
 *
 * The `type` + `meta` pair is the extension mechanism: a new content type
 * needs no schema change here (FR-CNT-07, ADR-003).
 *
 * @property int $id
 * @property int $module_id
 * @property string $title
 * @property string $slug
 * @property LessonType $type
 * @property string|null $summary
 * @property string|null $body
 * @property int $position
 * @property int|null $duration_seconds
 * @property bool $is_published
 * @property array<string, mixed>|null $meta
 */
class Lesson extends Model
{
    /** @use HasFactory<\Database\Factories\LessonFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * `module_id` is absent — moving a lesson between modules is a reorder
     * Action, not a mass-assign.
     *
     * `body` IS fillable, but it holds HTML and MUST be sanitised against an
     * allow-list before it reaches here (NFR-SEC-06). Sanitising on save
     * rather than on render is deliberate: storing hostile markup and hoping
     * every read path escapes it is how XSS survives a refactor.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'type',
        'summary',
        'body',
        'position',
        'duration_seconds',
        'is_published',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LessonType::class,
            'position' => 'integer',
            'duration_seconds' => 'integer',
            'is_published' => 'boolean',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * The owning course, reached through the module.
     *
     * Every access decision needs this: MediaFilePolicy and the Phase 6
     * access gate both resolve "which course does this belong to?" before
     * asking "is this user enrolled in it?".
     */
    public function course(): ?Course
    {
        return $this->module?->course;
    }

    /**
     * @return MorphMany<MediaFile, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany(MediaFile::class, 'attachable')->orderBy('position');
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
    public function scopeOfType(Builder $query, LessonType $type): void
    {
        $query->where('type', $type);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position');
    }

    /**
     * Does this lesson type require media that is not yet attached?
     *
     * Consumed by CoursePublishValidator in Phase 5 — a video lesson with no
     * video would give a paying student an empty page.
     */
    public function isMissingRequiredMedia(): bool
    {
        return $this->type->requiresMedia() && $this->media()->doesntExist();
    }
}
