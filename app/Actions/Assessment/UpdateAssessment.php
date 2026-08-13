<?php

declare(strict_types=1);

namespace App\Actions\Assessment;

use App\Models\Assessment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Support\Facades\DB;

/**
 * Update an assessment's settings (FR-ASMT-03). NOT handled here: `type`
 * (an assessment never moves between quiz/test after creation — the
 * assessable it's attached to fixes that), `total_marks`/`questions_count`
 * (derived caches, FR-ASMT-06), `is_published` (a validated transition via
 * PublishAssessment/UnpublishAssessment).
 */
final class UpdateAssessment
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Assessment $assessment, array $attributes, User $actor): Assessment
    {
        $before = $assessment->only(['title', 'passing_percentage', 'time_limit_minutes', 'max_attempts']);

        DB::transaction(function () use ($assessment, $attributes): void {
            $fillable = [];

            foreach ([
                'title', 'passing_percentage', 'time_limit_minutes', 'max_attempts',
                'scoring_policy', 'shuffle_questions', 'shuffle_options',
                'answer_reveal', 'negative_marking_enabled', 'available_from', 'available_until',
            ] as $key) {
                if (array_key_exists($key, $attributes)) {
                    $fillable[$key] = $attributes[$key];
                }
            }

            if (array_key_exists('instructions', $attributes)) {
                $fillable['instructions'] = $this->sanitizer->plainText(
                    $attributes['instructions'] === null ? null : (string) $attributes['instructions'],
                );
            }

            $assessment->fill($fillable)->save();
        });

        $assessment->refresh();

        $this->audit->record(
            action: 'assessment.updated',
            actor: $actor,
            subject: $assessment,
            changes: ['before' => $before, 'after' => $assessment->only(array_keys($before))],
            description: "Updated assessment \"{$assessment->title}\".",
        );

        return $assessment;
    }
}
