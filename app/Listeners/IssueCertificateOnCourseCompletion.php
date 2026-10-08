<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Certificate\IssueCertificate;
use App\Events\CourseCompleted;

/**
 * Award the certificate when a student finishes a course (design handoff §7).
 *
 * CourseCompleted is the right seam and the only one: it fires exactly once per
 * enrolment, guarded by `completed_at`, and for a course with a final test it
 * fires only after the test is passed — not merely when every lesson is ticked
 * (AC-31). Listening to lesson progress instead would hand out credentials
 * nobody had earned.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * SYNCHRONOUS, INSIDE THE COMPLETION TRANSACTION.
 *
 * Issuance only inserts a certificate row; PDF rendering happens on download.
 * It therefore runs synchronously in the transaction that records completion.
 * If issuance fails, the transaction rolls back both writes and a later
 * progress recalculation can safely retry the transition. The completion
 * event's mail listener only queues work, and the queue releases it after
 * this transaction commits.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class IssueCertificateOnCourseCompletion
{
    public function __construct(private readonly IssueCertificate $issue) {}

    public function handle(CourseCompleted $event): void
    {
        $this->issue->handle($event->enrollment);
    }
}
