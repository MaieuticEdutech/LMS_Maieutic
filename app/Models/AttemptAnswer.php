<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AttemptAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's answer to one question, within one {@see AssessmentAttempt}
 * (architecture.md §6.4, FR-ASMT-11 — autosaved as the student progresses).
 *
 * `is_correct` here is the GRADED RESULT of this specific answer, not the
 * answer key — see this migration's docblock for why it is deliberately not
 * `$hidden`, unlike {@see QuestionOption::$hidden}.
 *
 * @property int $id
 * @property int $attempt_id
 * @property int $question_id
 * @property list<int>|null $selected_option_ids
 * @property string|null $answer_text
 * @property bool|null $is_correct
 * @property string|null $marks_awarded
 * @property CarbonImmutable $answered_at
 */
class AttemptAnswer extends Model
{
    /** @use HasFactory<AttemptAnswerFactory> */
    use HasFactory;

    /**
     * `attempt_id`, `question_id` — owning-relation convention used
     * throughout this track. `is_correct`, `marks_awarded` — grading
     * results, written only by `GradingService` (Phase 8), never from
     * request input (NFR-SEC-21), same reasoning as
     * `AssessmentAttempt::$fillable` excluding its score fields.
     *
     * `selected_option_ids`, `answer_text` and `answered_at` ARE fillable —
     * unlike the fields above, these are the student's own submitted
     * content, legitimately set by the autosave action from validated
     * request input (the same shape as `Order`'s buyer fields being
     * fillable while its money columns are not).
     *
     * @var list<string>
     */
    protected $fillable = [
        'selected_option_ids',
        'answer_text',
        'answered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'selected_option_ids' => 'array',
            'is_correct' => 'boolean',
            'marks_awarded' => 'decimal:2',
            'answered_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<AssessmentAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class);
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
