<?php

declare(strict_types=1);

namespace App\Actions\Assessment;

use App\Enums\AnswerRevealPolicy;
use App\Enums\AssessmentType;
use App\Enums\ScoringPolicy;
use App\Models\Assessment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Create an assessment attached to a Lesson, Module or Course (FR-ASMT-01).
 *
 * Always created UNPUBLISHED — same reasoning as CreateCourse/CreateModule:
 * an assessment with no questions is not something a student should ever
 * reach, and publishing is a separate, validated transition (PublishAssessment).
 */
final class CreateAssessment
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @param  array{
     *     type: AssessmentType,
     *     title: string,
     *     instructions?: string|null,
     *     passing_percentage: int,
     *     time_limit_minutes?: int|null,
     *     max_attempts?: int|null,
     *     scoring_policy?: ScoringPolicy,
     *     shuffle_questions?: bool,
     *     shuffle_options?: bool,
     *     answer_reveal?: AnswerRevealPolicy,
     *     negative_marking_enabled?: bool,
     * }  $attributes
     */
    public function handle(Model $assessable, array $attributes, User $actor): Assessment
    {
        $assessment = DB::transaction(function () use ($assessable, $attributes, $actor): Assessment {
            $assessment = new Assessment;

            $assessment->fill([
                'type' => $attributes['type'],
                'title' => $attributes['title'],
                'instructions' => $this->sanitizer->plainText($attributes['instructions'] ?? null),
                'passing_percentage' => $attributes['passing_percentage'],
                'time_limit_minutes' => $attributes['time_limit_minutes'] ?? null,
                'max_attempts' => $attributes['max_attempts'] ?? null,
                'scoring_policy' => $attributes['scoring_policy'] ?? ScoringPolicy::Highest,
                'shuffle_questions' => $attributes['shuffle_questions'] ?? false,
                'shuffle_options' => $attributes['shuffle_options'] ?? false,
                'answer_reveal' => $attributes['answer_reveal'] ?? AnswerRevealPolicy::AfterSubmit,
                'negative_marking_enabled' => $attributes['negative_marking_enabled'] ?? false,
                'is_published' => false,
            ]);

            $assessment->assessable()->associate($assessable);
            $assessment->created_by = $actor->getKey();
            $assessment->save();

            return $assessment;
        });

        $this->audit->record(
            action: 'assessment.created',
            actor: $actor,
            subject: $assessment,
            description: "Created assessment \"{$assessment->title}\".",
        );

        return $assessment;
    }
}
