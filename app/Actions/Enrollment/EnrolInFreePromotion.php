<?php

declare(strict_types=1);

namespace App\Actions\Enrollment;

use App\Enums\EnrollmentSource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Catalogue\Promotion;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * A student claims a published course for free while the promotion is running.
 *
 * This is a THIRD audited path to an enrollment (planning.md §16.4). It creates
 * nothing itself: it checks the promotion, then hands to GrantEnrollment, the
 * only door, which is idempotent, writes the audit entry and fires
 * EnrollmentGranted. The actor is the student, so the audit log says who asked.
 *
 * The browser decides nothing here: no client-supplied flag or price is read.
 */
final class EnrolInFreePromotion
{
    public function __construct(
        private readonly Promotion $promotion,
        private readonly GrantEnrollment $grant,
    ) {}

    /**
     * @throws AuthorizationException When the promotion is over, the course is not
     *                                published, or the user is not an active student.
     */
    public function handle(User $student, Course $course): Enrollment
    {
        $ends = $this->promotion->endsAt();

        if ($ends === null || ! $this->promotion->active()) {
            throw new AuthorizationException('The free promotion is not running.');
        }

        if (! $course->isPublished()) {
            throw new AuthorizationException('This course is not open for enrolment.');
        }

        if (! $student->isStudent() || ! $student->canAuthenticate()) {
            throw new AuthorizationException('Only active students can enrol.');
        }

        return $this->grant->handle(
            student: $student,
            course: $course,
            source: EnrollmentSource::FreePromotion,
            actor: $student,
            reason: 'Free launch promotion until '.$ends->format('j M Y'),
        );
    }
}
