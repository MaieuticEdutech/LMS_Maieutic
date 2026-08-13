<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AttemptStatus;
use App\Events\AttemptGraded;
use App\Notifications\AssessmentResultNotification;

/**
 * Emails a student their marks once an attempt is graded (FR-MAIL-07).
 *
 * Attaches to Phase 8's `AttemptGraded` rather than living inside
 * `GradingService`, which belongs to Track B. Keeping mail out of the grading
 * path means the two fail independently: a broken template can never stop an
 * attempt being marked, and a student must get their result on screen whether
 * or not the email goes out (AC-33).
 *
 * NOT ShouldQueue, deliberately. The notification is the queued unit; queueing
 * this as well would add a job whose only work is to dispatch another job.
 *
 * ONE EMAIL PER GRADING, and the event is what guarantees it — `AttemptGraded`
 * fires when an attempt moves INTO a graded state, not every time one is read.
 * A student retaking a quiz gets one email per attempt, which is correct: each
 * is a separate result.
 */
final class SendAssessmentResultNotification
{
    public function handle(AttemptGraded $event): void
    {
        $attempt = $event->attempt;

        /*
         * Loaded explicitly. Model::preventLazyLoading is active outside
         * production, so a lazy read here would throw on a queue worker — the
         * one place nobody is watching it happen.
         */
        $attempt->loadMissing(['user', 'assessment']);

        $student = $attempt->user;
        $assessment = $attempt->assessment;

        if ($student === null || $assessment === null) {
            // The attempt outlived one of its parents. There is nobody to
            // write to, and inventing a recipient is worse than silence.
            return;
        }

        /*
         * An ungraded attempt has no result to report. This is reachable: the
         * event carries whatever state grading left behind, and a grading run
         * that failed part-way must not produce an email announcing a null
         * score as "0%" — a student reading that would believe they had failed
         * something they never sat.
         */
        if ($attempt->score_percentage === null) {
            return;
        }

        $student->notify(new AssessmentResultNotification(
            assessmentTitle: $assessment->title,
            // The column is decimal:2; a student wants "68%", not "68.00%".
            scorePercentage: (int) round((float) $attempt->score_percentage),
            passed: $attempt->is_passed === true,
            attemptKey: $attempt->getRouteKey(),
            // Distinguishes "you pressed submit" from "the timer did it for
            // you", which is the difference between an expected email and a
            // baffling one (FR-ASMT-10).
            ranOutOfTime: $attempt->status === AttemptStatus::Expired,
        ));
    }
}
