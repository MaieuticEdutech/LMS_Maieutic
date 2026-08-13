<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * An assessment cannot be deleted because students have attempted it —
 * `assessment_attempts.assessment_id` is RESTRICT, not CASCADE, precisely so
 * a deleted assessment cannot retroactively erase a student's grading
 * history (see that migration's docblock). Its own type, mirroring
 * CourseDeletionException, so the UI can catch precisely this rather than a
 * raw database constraint violation.
 */
final class AssessmentDeletionException extends RuntimeException {}
