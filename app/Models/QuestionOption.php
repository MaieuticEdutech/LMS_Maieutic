<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QuestionOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An answer option belonging to a {@see Question}. `is_correct` is the
 * answer key (NFR-SEC-21, AC-23).
 *
 * `is_correct` IS $hidden — a Phase-3 defence-in-depth default, not the
 * whole mechanism. Hiding only affects array/JSON serialisation
 * (`toArray()`, `toJson()`, and anything that funnels through those, e.g. a
 * careless `return $question->load('options');` from a future controller);
 * it does NOT affect direct attribute access (`$option->is_correct`,
 * `$option->getAttribute('is_correct')`), so Blade views, admin/instructor
 * authoring screens and grading logic are unaffected.
 *
 * The actual policy-aware reveal to a student — honouring
 * {@see \App\Enums\AnswerRevealPolicy} and only after submission — is a
 * dedicated `QuestionPresenter`, already named in architecture.md §6.4 and
 * §12.2. That presenter is Phase 8 work and is expected to call
 * `makeVisible('is_correct')` (or build its own array) only once policy and
 * submission state allow it. Do not reach for a generic API Resource class
 * for this column instead of the presenter — none is specified anywhere in
 * the architecture docs, and the reveal is inherently conditional on
 * request-time state ($hidden alone cannot express "after this student
 * submits"), which is exactly why a presenter, not a static property, is the
 * documented mechanism.
 *
 * @property int $id
 * @property int $question_id
 * @property string $body
 * @property bool $is_correct
 * @property int $position
 */
class QuestionOption extends Model
{
    /** @use HasFactory<QuestionOptionFactory> */
    use HasFactory;

    /**
     * `question_id` is deliberately absent — set via the owning relation
     * (`$question->options()->create([...])`), the same convention already
     * used for `Question::assessment_id` and `InstructorProfile::user_id`.
     *
     * @var list<string>
     */
    protected $fillable = [
        'body',
        'is_correct',
        'position',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'is_correct',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
