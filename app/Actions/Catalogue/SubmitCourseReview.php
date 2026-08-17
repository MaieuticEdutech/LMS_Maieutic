<?php

declare(strict_types=1);

namespace App\Actions\Catalogue;

use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Services\Enrollment\EnrollmentAccessService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Record or update a student's rating of a course (design handoff §2).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * THE SOLE WRITER OF `course_reviews` AND OF THE TWO COUNTERS ON `courses`.
 *
 * The counters are only trustworthy if exactly one place maintains them. A
 * second writer that inserted a review without adjusting rating_sum would leave
 * an average that is wrong in a way nothing would detect — it would just be
 * quietly, permanently off, on a page that sells the course.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * ONLY SOMEONE WITH ACCESS MAY RATE. Checked through
 * EnrollmentAccessService::grantsAccess() rather than by looking at the
 * enrollment's status here — that service is the single definition of "has
 * access" (rule S-8), and a review is exactly the kind of secondary feature
 * that would otherwise grow its own slightly-different copy of the rule.
 *
 * EDITING IS ALLOWED, and the counters follow. Someone who rates a course two
 * lessons in and changes their mind at the end should be able to say so; the
 * alternative is a permanent first impression, which is worse data as well as
 * worse manners.
 */
final class SubmitCourseReview
{
    public function __construct(private readonly EnrollmentAccessService $access) {}

    public function handle(Enrollment $enrollment, int $rating, ?string $body = null): CourseReview
    {
        if ($rating < 1 || $rating > 5) {
            // Also a CHECK constraint (ADR-012). Rejected here so the caller
            // gets a usable message rather than a driver exception.
            throw new InvalidArgumentException('A rating must be between 1 and 5.');
        }

        $enrollment->loadMissing(['user', 'course']);

        $user = $enrollment->user ?? throw new RuntimeException(
            "Enrollment #{$enrollment->getKey()} has no user — the FK constraint should make this impossible.",
        );

        $course = $enrollment->course ?? throw new RuntimeException(
            "Enrollment #{$enrollment->getKey()} has no course — the FK constraint should make this impossible.",
        );

        if (! $this->access->grantsAccess($user, $course)) {
            throw new RuntimeException('Only a student with access to this course may review it.');
        }

        $trimmed = $body === null ? null : trim($body);

        return DB::transaction(function () use ($enrollment, $user, $course, $rating, $trimmed): CourseReview {
            /*
             * Locked for the duration, so a second submission from another tab
             * waits rather than reading a stale rating and applying the wrong
             * delta to the counters.
             */
            $existing = CourseReview::query()
                ->where('enrollment_id', $enrollment->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing instanceof CourseReview) {
                // The DELTA, not the new value: the count does not change on an
                // edit, and the sum moves by the difference.
                $delta = $rating - $existing->rating;

                $existing->fill(['rating' => $rating, 'body' => $trimmed === '' ? null : $trimmed])->save();

                if ($delta !== 0) {
                    $this->adjust($course, $delta, 0);
                }

                return $existing;
            }

            $review = CourseReview::query()->create([
                'enrollment_id' => $enrollment->getKey(),
                'user_id' => $user->getKey(),
                'course_id' => $course->getKey(),
                'rating' => $rating,
                'body' => $trimmed === '' ? null : $trimmed,
            ]);

            $this->adjust($course, $rating, 1);

            return $review;
        });
    }

    /**
     * Move the cached counters by a delta, in the database.
     *
     * `incrementEach` rather than read-modify-write in PHP: two reviews landing
     * at the same moment must both count, and `$course->rating_sum + $rating`
     * would lose one of them. It also emits `column = column + ?` with a bound
     * parameter, rather than SQL assembled by string concatenation.
     *
     * `$sumDelta` is negative when an edit lowers a rating; `$countDelta` is
     * only ever 0 or 1, so the unsigned count column can never be driven below
     * zero.
     */
    private function adjust(Course $course, int $sumDelta, int $countDelta): void
    {
        Course::query()->whereKey($course->getKey())->incrementEach([
            'rating_sum' => $sumDelta,
            'rating_count' => $countDelta,
        ]);
    }
}
