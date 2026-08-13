<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuestionType;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single question belonging to an {@see Assessment}. Correct-answer data
 * lives on {@see QuestionOption} (or in `meta` for short-answer questions)
 * and is never present on this model's own columns (FR-ASMT-07).
 *
 * @property int $id
 * @property int $assessment_id
 * @property QuestionType $type
 * @property string $body
 * @property string|null $explanation
 * @property string $marks
 * @property string $negative_marks
 * @property int $position
 * @property array<string, mixed>|null $meta
 */
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    /**
     * `assessment_id` is deliberately absent — set via the owning relation
     * (`$assessment->questions()->create([...])`), the same convention this
     * codebase already uses for `InstructorProfile::user_id`.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'body',
        'explanation',
        'marks',
        'negative_marks',
        'position',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'marks' => 'decimal:2',
            'negative_marks' => 'decimal:2',
            'position' => 'integer',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return HasMany<QuestionOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('position');
    }

    /**
     * @return HasMany<AttemptAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }
}
