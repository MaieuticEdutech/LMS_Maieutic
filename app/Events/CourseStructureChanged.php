<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Course;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A course gained or lost published content (FR-PROG-09, AC-30).
 *
 * Every enrollment in this course now has a stale percentage, because the
 * denominator changed. Publishing a tenth lesson to a course where a student
 * had completed all nine must move them from 100% to 90% — silently leaving
 * them at 100% would tell them they had finished a course they had not.
 *
 * The correction is not optional or cosmetic: course completion, certificates
 * and the completion email all read that figure.
 *
 * Listened to by a batched recalculation rather than handled inline, because
 * a popular course can have thousands of enrollments and an author publishing
 * a lesson must not wait for them.
 */
final class CourseStructureChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Course $course,
        public readonly string $reason,
    ) {}
}
