<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A student finished a lesson (FR-PROG-05).
 *
 * FIRES ONCE PER LESSON, EVER. RecordLessonProgress dispatches this only on
 * the transition from not-complete to complete, never on a repeat report for
 * an already-finished lesson. A video that keeps reporting position after the
 * threshold would otherwise fire this on every tick, and each one queues a
 * course recalculation.
 *
 * Listeners: course progress recalculation, and from Phase 8 the assessment
 * unlock rules. The payload is explicit so a listener never has to re-derive
 * which lesson or which enrollment (seam S-4).
 */
final class LessonCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly Lesson $lesson,
        public readonly LessonProgress $progress,
    ) {}
}
