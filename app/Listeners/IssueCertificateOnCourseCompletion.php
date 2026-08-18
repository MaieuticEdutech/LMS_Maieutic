<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Certificate\IssueCertificate;
use App\Events\CourseCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

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
 * QUEUED, AND A FAILURE HERE MUST NOT UNDO THE COMPLETION.
 *
 * Finishing a course is the student's achievement; the certificate is a
 * consequence of it. If issuing fails — a database hiccup, an exhausted
 * connection — the completion itself, and the email that celebrates it, must
 * still stand. So this runs on the queue and swallows its own failure into the
 * log rather than letting it propagate.
 *
 * Nothing is lost by that: IssueCertificate is idempotent, so a retry or a
 * later backfill produces the certificate that was missed, with the correct
 * award date, because issued_at comes from the enrolment rather than from the
 * clock at issue time.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class IssueCertificateOnCourseCompletion implements ShouldQueue
{
    public function __construct(private readonly IssueCertificate $issue) {}

    public function handle(CourseCompleted $event): void
    {
        try {
            $this->issue->handle($event->enrollment);
        } catch (Throwable $e) {
            /*
             * report(), not Log::error(). A swallowed exception has to reach
             * whatever error tracking is configured — the whole risk of catching
             * it here is that a missed award becomes invisible, and a line in a
             * log file nobody reads is close to invisible. report() goes to the
             * handler, so it lands wherever real failures land.
             */
            report($e);

            Log::warning('A certificate could not be issued for a completed course.', [
                'enrollment_id' => $event->enrollment->getKey(),
            ]);
        }
    }
}
