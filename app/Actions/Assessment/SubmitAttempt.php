<?php

declare(strict_types=1);

namespace App\Actions\Assessment;

use App\Enums\AttemptStatus;
use App\Events\AttemptGraded;
use App\Exceptions\AttemptNotAllowedException;
use App\Models\AssessmentAttempt;
use App\Models\User;
use App\Services\Assessment\GradingService;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Submit and grade an attempt (FR-ASMT-12, architecture.md §10.3's sequence
 * diagram — this class IS that diagram).
 *
 * IDEMPOTENT: a second call against an already-graded attempt returns it
 * unchanged rather than throwing or re-grading — tolerates a double-click or
 * a network retry on the submit button, both realistic on a timed screen a
 * student is anxious about.
 *
 * ACCEPTS in_progress OR expired: by the time this runs, SaveAnswer has
 * already refused every post-deadline save, so an expired attempt's saved
 * answers are already exactly "everything saved before the deadline"
 * (FR-ASMT-10) — grading it here needs no separate filtering step.
 *
 * ROW LOCKED FOR UPDATE inside the transaction so two concurrent submit
 * requests for the same attempt cannot both grade it.
 */
final class SubmitAttempt
{
    public function __construct(
        private readonly GradingService $grading,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * `$actor` is nullable: ExpireStaleAttempts submits on a student's
     * behalf when they never returned to click submit, and that grading is
     * system-initiated, not performed by any authenticated user — the audit
     * entry should say so rather than attribute it to whoever happened to
     * be resolved.
     *
     * @throws AttemptNotAllowedException
     */
    public function handle(AssessmentAttempt $attempt, ?User $actor = null): AssessmentAttempt
    {
        if (! in_array($attempt->status, [AttemptStatus::InProgress, AttemptStatus::Expired, AttemptStatus::Graded], true)) {
            throw new AttemptNotAllowedException('This attempt cannot be submitted.');
        }

        $performedGrading = false;

        $graded = DB::transaction(function () use ($attempt, &$performedGrading): AssessmentAttempt {
            /** @var AssessmentAttempt $locked */
            $locked = AssessmentAttempt::query()->whereKey($attempt->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === AttemptStatus::Graded) {
                return $locked;
            }

            $now = now();
            $submittedAt = $locked->submitted_at ?? $now;
            $result = $this->grading->grade($locked);

            $locked->forceFill([
                'status' => AttemptStatus::Graded,
                'submitted_at' => $submittedAt,
                'graded_at' => $now,
                'score_marks' => $result['scoreMarks'],
                'max_marks' => $result['maxMarks'],
                'score_percentage' => $result['percentage'],
                'is_passed' => $result['passed'],
                /*
                 * START to SUBMIT, in that order. It was written the other way
                 * round — `$submittedAt->diffInSeconds($started_at)` — which
                 * Carbon returns SIGNED, so it was always negative and the
                 * max(0, …) floored every attempt to zero seconds.
                 *
                 * Silent, and wrong in the direction nobody checks: the column
                 * existed, was populated, and read 0 for every attempt ever
                 * submitted. Phase 13's reporting would have inherited it as
                 * "students spend no time on assessments".
                 */
                'time_spent_seconds' => max(0, (int) $locked->started_at->diffInSeconds($submittedAt)),
            ])->save();

            $performedGrading = true;

            return $locked;
        });

        // Already-graded (idempotent replay) skips the audit + event — the
        // original submission already recorded both.
        if ($performedGrading) {
            $assessment = $graded->assessment;

            if ($assessment === null) {
                throw new RuntimeException(
                    "Attempt #{$graded->id} has no assessment — the FK constraint should make this impossible.",
                );
            }

            $this->audit->record(
                action: 'attempt.graded',
                actor: $actor,
                subject: $graded,
                description: sprintf(
                    'Attempt #%d on "%s" graded: %s/%s (%s%%).',
                    $graded->attempt_number,
                    $assessment->title,
                    $graded->score_marks,
                    $graded->max_marks,
                    $graded->score_percentage,
                ),
            );

            AttemptGraded::dispatch($graded);
        }

        return $graded;
    }
}
