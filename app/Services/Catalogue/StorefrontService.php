<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Enums\CourseStatus;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The figures the storefront shows (Spotlight × Canvas): Top 10, badges,
 * instructor and platform stats.
 *
 * Every number comes from real rows. None of it decides access: "has access"
 * is EnrollmentAccessService alone (rule S-8). These are popularity counts, so
 * they count every enrolment that was ever granted, whatever its status now.
 */
final class StorefrontService
{
    /** "New" lasts this many days after publishing. */
    public const NEW_FOR_DAYS = 30;

    /** "Bestseller" goes to this many courses, ranked over BESTSELLER_DAYS. */
    public const BESTSELLER_COUNT = 3;

    public const BESTSELLER_DAYS = 30;

    /**
     * Published courses with the most enrolments in the last 7 days, most
     * first. Ties go to the higher-rated, then the newer course, so the order
     * is stable between page loads.
     *
     * @return Collection<int, Course>
     */
    public function topThisWeek(int $limit = 10): Collection
    {
        $counts = Enrollment::query()
            ->select('course_id', DB::raw('count(*) as recent'))
            ->where('enrolled_at', '>=', now()->subDays(7))
            ->groupBy('course_id');

        return Course::query()
            ->published()
            ->joinSub($counts, 'recent', 'recent.course_id', '=', 'courses.id')
            ->select('courses.*', 'recent.recent as recent_enrolments')
            ->orderByDesc('recent.recent')
            ->orderByDesc('courses.rating_count')
            ->orderByDesc('courses.published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Ids of the current Bestsellers: the most-enrolled published courses over
     * the last BESTSELLER_DAYS. Cached briefly: it is the same answer for every
     * card on every page.
     *
     * @return list<int>
     */
    public function bestsellerIds(): array
    {
        /** @var list<int> */
        return Cache::remember('storefront.bestsellers', 600, function (): array {
            return Enrollment::query()
                ->join('courses', 'courses.id', '=', 'enrollments.course_id')
                ->where('courses.status', CourseStatus::Published->value)
                ->where('enrollments.enrolled_at', '>=', now()->subDays(self::BESTSELLER_DAYS))
                ->groupBy('enrollments.course_id')
                ->orderByRaw('count(*) desc')
                ->limit(self::BESTSELLER_COUNT)
                ->pluck('enrollments.course_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all();
        });
    }

    /**
     * The badge a course card shows, or null. Bestseller wins over New.
     *
     * @param  list<int>|null  $bestsellers  Pass bestsellerIds() once when
     *                                       labelling a whole grid.
     */
    public function badgeFor(Course $course, ?array $bestsellers = null): ?string
    {
        $bestsellers ??= $this->bestsellerIds();

        if (in_array((int) $course->getKey(), $bestsellers, true)) {
            return 'Bestseller';
        }

        if ($course->published_at !== null && $course->published_at->gte(now()->subDays(self::NEW_FOR_DAYS))) {
            return 'New';
        }

        return null;
    }

    /**
     * Totals for an instructor's public profile block.
     *
     * The rating is weighted by review count across their published courses,
     * so one course with a single 5-star review cannot outweigh a course with
     * five hundred reviews.
     *
     * @return array{courses: int, learners: int, rating: float|null, reviews: int}
     */
    public function instructorStats(User $instructor): array
    {
        $courseIds = Course::query()
            ->published()
            ->whereHas('instructors', fn ($query) => $query->whereKey($instructor->getKey()))
            ->pluck('id');

        [$total, $reviews] = $this->ratingTotals(Course::query()->whereIn('id', $courseIds)->toBase());

        return [
            'courses' => $courseIds->count(),
            'learners' => Enrollment::query()->whereIn('course_id', $courseIds)->distinct()->count('user_id'),
            'rating' => $reviews > 0 ? round($total / $reviews, 1) : null,
            'reviews' => $reviews,
        ];
    }

    /**
     * Headline numbers for the landing page. Cached for 10 minutes: they move
     * slowly and every visitor sees the same ones.
     *
     * @return array{courses: int, learners: int, certificates: int, rating: float|null}
     */
    public function platformStats(): array
    {
        /** @var array{courses: int, learners: int, certificates: int, rating: float|null} */
        return Cache::remember('storefront.platform-stats', 600, function (): array {
            [$total, $reviews] = $this->ratingTotals(Course::query()->published()->toBase());

            return [
                'courses' => Course::query()->published()->count(),
                'learners' => Enrollment::query()->distinct()->count('user_id'),
                'certificates' => Certificate::query()->count(),
                'rating' => $reviews > 0 ? round($total / $reviews, 1) : null,
            ];
        });
    }

    /**
     * How many reviews gave each star, for the rating bars on a course page.
     * Keys 5 down to 1; a star nobody gave is 0, never missing.
     *
     * @return array<int, int>
     */
    public function ratingBreakdown(Course $course): array
    {
        /** @var array<int, int> $counts */
        $counts = CourseReview::query()
            ->where('course_id', $course->getKey())
            ->selectRaw('rating, count(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        $breakdown = [];

        foreach ([5, 4, 3, 2, 1] as $star) {
            $breakdown[$star] = $counts[$star] ?? 0;
        }

        return $breakdown;
    }

    /**
     * The newest written reviews of published courses, for the landing page.
     * A star with no words is not a quote, so it is left out.
     *
     * @return Collection<int, CourseReview>
     */
    public function recentReviews(int $limit = 3): Collection
    {
        return CourseReview::query()
            ->whereNotNull('body')
            ->whereIn('course_id', Course::query()->published()->select('id'))
            ->with(['user', 'course'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Sum of rating points and of rating counts over a set of courses.
     *
     * @return array{0: int, 1: int}
     */
    private function ratingTotals(QueryBuilder $courses): array
    {
        $row = $courses
            ->selectRaw('coalesce(sum(rating_sum), 0) as points_total, coalesce(sum(rating_count), 0) as ratings_total')
            ->first();

        return [
            (int) ($row->points_total ?? 0),
            (int) ($row->ratings_total ?? 0),
        ];
    }
}
