<?php

declare(strict_types=1);

namespace App\Actions\Assessment;

use App\Enums\AttemptStatus;
use App\Exceptions\AttemptNotAllowedException;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Assessment\AttemptClock;
use App\Services\Audit\AuditLogger;
use App\Services\Enrollment\EnrollmentAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Start a new attempt (FR-ASMT-09, architecture.md §10.2). The policy
 * (AttemptPolicy::start) only checked "is this a student, is the assessment
 * published" — the fuller conditions live here, re-asserting domain
 * invariants the way every Action in this codebase does (architecture.md
 * §8.2): enrolled, within the availability window, attempt count below the
 * limit, and no second in-progress attempt (FR-ASMT-16, AC-26).
 *
 * ACCESS GOES THROUGH EnrollmentAccessService, NEVER A LOCAL QUERY (rule S-8)
 * — resolved through Assessment::resolveCourse() the same way every other
 * content-access decision resolves its owning course first.
 */
final class StartAttempt
{
    public function __construct(
        private readonly EnrollmentAccessService $access,
        private readonly AttemptClock $clock,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws AttemptNotAllowedException
     */
    public function handle(Assessment $assessment, User $actor): AssessmentAttempt
    {
        if (! $assessment->is_published) {
            throw new AttemptNotAllowedException('This assessment is not published yet.');
        }

        $now = CarbonImmutable::now();

        if ($assessment->available_from !== null && $now->lessThan($assessment->available_from)) {
            throw new AttemptNotAllowedException('This assessment is not open yet.');
        }

        if ($assessment->available_until !== null && $now->greaterThan($assessment->available_until)) {
            throw new AttemptNotAllowedException('This assessment is no longer available.');
        }

        $course = $assessment->resolveCourse();

        if ($course === null || ! $this->access->grantsAccess($actor, $course)) {
            throw new AttemptNotAllowedException('You do not have access to this assessment.');
        }

        // UNIQUE(user_id, course_id) — at most one row, and grantsAccess()
        // having returned true means it exists and is the one that granted
        // it.
        $enrollment = Enrollment::query()
            ->where('user_id', $actor->getKey())
            ->where('course_id', $course->getKey())
            ->first();

        if ($enrollment === null) {
            // Reachable only for an admin/instructor whom grantsAccess()
            // permits without an enrollment row — they administer or teach
            // the course, not take it. There is nothing to attach an
            // attempt to.
            throw new AttemptNotAllowedException('Only an enrolled student can attempt this assessment.');
        }

        $attemptCount = AssessmentAttempt::query()
            ->where('assessment_id', $assessment->getKey())
            ->where('user_id', $actor->getKey())
            ->count();

        if ($assessment->max_attempts !== null && $attemptCount >= $assessment->max_attempts) {
            throw new AttemptNotAllowedException('You have used every attempt allowed for this assessment.');
        }

        // Pre-check for a friendly message; the partial unique index below
        // is the actual guarantee against a concurrent double-start
        // (FR-ASMT-16, AC-26).
        $hasInProgress = AssessmentAttempt::query()
            ->where('assessment_id', $assessment->getKey())
            ->where('user_id', $actor->getKey())
            ->where('status', AttemptStatus::InProgress)
            ->exists();

        if ($hasInProgress) {
            throw new AttemptNotAllowedException('You already have an attempt in progress.');
        }

        $questionIds = array_values($assessment->questions()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all());

        if ($assessment->shuffle_questions) {
            shuffle($questionIds);
        }

        try {
            $attempt = DB::transaction(function () use ($assessment, $actor, $enrollment, $attemptCount, $questionIds, $now): AssessmentAttempt {
                $attempt = new AssessmentAttempt;

                $attempt->fill([
                    'started_at' => $now,
                    'expires_at' => $this->clock->computeExpiry($assessment, $now),
                    'time_spent_seconds' => 0,
                ]);

                $attempt->ulid = (string) Str::ulid();
                $attempt->assessment()->associate($assessment);
                $attempt->user()->associate($actor);
                $attempt->enrollment()->associate($enrollment);
                $attempt->attempt_number = $attemptCount + 1;
                $attempt->status = AttemptStatus::InProgress;
                $attempt->question_order = $questionIds;
                $attempt->save();

                return $attempt;
            });
        } catch (UniqueConstraintViolationException) {
            // The race window the partial unique index closes — see class
            // docblock.
            throw new AttemptNotAllowedException('You already have an attempt in progress.');
        }

        $this->audit->record(
            action: 'attempt.started',
            actor: $actor,
            subject: $attempt,
            description: "Started attempt #{$attempt->attempt_number} on \"{$assessment->title}\".",
        );

        return $attempt;
    }
}
