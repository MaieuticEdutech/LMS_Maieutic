<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Enrollment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A student finished an entire course (FR-PROG-08, AC-31).
 *
 * FIRES ONCE. The enrollment moves to `completed` with a `completed_at`, and
 * the guard is that timestamp: a recalculation that finds the course still at
 * 100% does not re-fire. Phase 11 attaches the completion email, and a
 * student receiving it three times because a lesson was republished would be
 * a poor way to mark the occasion.
 *
 * Access is NOT withdrawn on completion — `completed` still grants access
 * (EnrollmentAccessService). Finishing a course is not a reason to lose the
 * material you paid for.
 */
final class CourseCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Enrollment $enrollment) {}
}
