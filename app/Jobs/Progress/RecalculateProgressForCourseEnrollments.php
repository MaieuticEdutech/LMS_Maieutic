<?php

declare(strict_types=1);

namespace App\Jobs\Progress;

use App\Models\Course;
use App\Services\Progress\ProgressCalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recompute every enrollment in a course after its curriculum changed
 * (FR-PROG-09, AC-30).
 *
 * Publishing a tenth lesson to a course where a student had completed nine
 * must move them from 100% to 90%. Unpublishing one moves them back up. The
 * denominator changed, so every stored percentage in that course is stale.
 *
 * BATCHED AND QUEUED because a popular course can hold thousands of
 * enrollments, and an author pressing "publish" must not wait for them. The
 * calculator chunks internally so this stays bounded in memory as well as in
 * time.
 *
 * On the `default` queue, not `critical`: a percentage that is a minute stale
 * is a cosmetic problem, and it must never delay a payment or an enrollment.
 */
final class RecalculateProgressForCourseEnrollments implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $courseId) {}

    public function handle(ProgressCalculator $calculator): void
    {
        $course = Course::query()->find($this->courseId);

        if ($course === null) {
            return;
        }

        $calculator->recalculateCourseEnrollments($course);
    }

    public function uniqueId(): string
    {
        return 'course:'.$this->courseId;
    }
}
