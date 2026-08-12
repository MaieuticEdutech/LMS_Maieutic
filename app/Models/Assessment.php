<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnswerRevealPolicy;
use App\Enums\AssessmentType;
use App\Enums\ScoringPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One engine for quizzes and tests alike (ADR-002) — `type` is the only
 * discriminator; `assessable` is the polymorphic attach point (Lesson,
 * Module or Course).
 *
 * @property int $id
 * @property string $assessable_type
 * @property int $assessable_id
 * @property AssessmentType $type
 * @property string $title
 * @property string|null $instructions
 * @property int $passing_percentage
 * @property int|null $time_limit_minutes
 * @property int|null $max_attempts
 * @property ScoringPolicy $scoring_policy
 * @property bool $shuffle_questions
 * @property bool $shuffle_options
 * @property AnswerRevealPolicy $answer_reveal
 * @property bool $negative_marking_enabled
 * @property string $total_marks
 * @property int $questions_count
 * @property bool $is_published
 * @property CarbonImmutable|null $available_from
 * @property CarbonImmutable|null $available_until
 * @property int|null $created_by
 */
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory;

    /**
     * `created_by` is an ownership field and is deliberately absent
     * (planning.md conventions — ownership fields are never fillable). It is
     * set explicitly, by name, from the authenticated actor inside an Action.
     *
     * `total_marks` and `questions_count` are derived caches (FR-ASMT-06) and
     * are likewise absent — they are recomputed, never posted from a form.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'title',
        'instructions',
        'passing_percentage',
        'time_limit_minutes',
        'max_attempts',
        'scoring_policy',
        'shuffle_questions',
        'shuffle_options',
        'answer_reveal',
        'negative_marking_enabled',
        'is_published',
        'available_from',
        'available_until',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AssessmentType::class,
            'scoring_policy' => ScoringPolicy::class,
            'answer_reveal' => AnswerRevealPolicy::class,
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'negative_marking_enabled' => 'boolean',
            'is_published' => 'boolean',
            'available_from' => 'immutable_datetime',
            'available_until' => 'immutable_datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function assessable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position');
    }

    /**
     * @return HasMany<AssessmentAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }
}
