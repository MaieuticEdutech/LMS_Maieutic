<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CourseCompleted;
use App\Notifications\CourseCompletedNotification;

/**
 * Emails a student when they finish a course (FR-MAIL-07, FR-PROG-08).
 *
 * Attaches to Phase 9's `CourseCompleted` rather than living inside
 * `ProgressCalculator`. Progress is a calculation and mail is a side effect;
 * wiring them through the event means a template error cannot leave a student's
 * completion unrecorded — the shape every other notification here follows, and
 * what AC-33 asks for.
 *
 * IDEMPOTENCY IS UPSTREAM, and it has to be. The calculator fires this only on
 * the transition into completion, guarded by `completed_at`, so republishing a
 * lesson and completing it again does not congratulate the student twice. There
 * is nothing this listener could do about a duplicate event that the guard does
 * not already do better.
 */
final class SendCourseCompletedNotification
{
    public function handle(CourseCompleted $event): void
    {
        $enrollment = $event->enrollment;

        // Explicit, not lazy: this runs on a worker where a lazy-loading
        // violation would surface as a failed job rather than a visible error.
        $enrollment->loadMissing(['user', 'course']);

        $student = $enrollment->user;
        $course = $enrollment->course;

        if ($student === null || $course === null) {
            return;
        }

        $student->notify(new CourseCompletedNotification(
            courseTitle: $course->title,
            lessonsCompleted: $enrollment->completed_lessons_count,
        ));
    }
}
