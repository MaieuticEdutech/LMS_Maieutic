<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use App\Services\Catalogue\Promotion;
use App\Services\Catalogue\StorefrontService;
use App\Services\Settings\BrandingService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;

/**
 * The public landing page. Gathers the real storefront figures (all of them
 * from StorefrontService / Promotion) and hands them to the view; a section
 * with nothing behind it is simply not rendered.
 */
final class HomeController extends Controller
{
    /** Relations every course card on the page reads, loaded once. */
    private const CARD_RELATIONS = ['category', 'thumbnail', 'instructors.instructorProfile.photo'];

    public function __invoke(StorefrontService $storefront, Promotion $promotion, BrandingService $branding): View
    {
        $popular = Course::query()
            ->published()
            ->with(self::CARD_RELATIONS)
            ->withCount('enrollments')
            ->orderByDesc('enrollments_count')
            ->orderByDesc('rating_count')
            ->orderByDesc('published_at')
            ->limit(8)
            ->get();

        $bestsellers = $storefront->bestsellerIds();

        $top = $storefront->topThisWeek(10)->load(self::CARD_RELATIONS);

        $stats = $storefront->platformStats();

        // The figures are real or absent: a zero or a missing average is left
        // out rather than printed.
        $figures = array_values(array_filter([
            $stats['courses'] > 0 ? ['value' => number_format($stats['courses']), 'label' => 'Published courses'] : null,
            $stats['learners'] > 0 ? ['value' => number_format($stats['learners']), 'label' => 'Learners enrolled'] : null,
            $stats['certificates'] > 0 ? ['value' => number_format($stats['certificates']), 'label' => 'Certificates issued'] : null,
            $stats['rating'] !== null ? ['value' => number_format($stats['rating'], 1).' / 5', 'label' => 'Average course rating'] : null,
        ]));

        // The people teaching what is on this page, with their real numbers.
        $instructors = $popular->merge($top)
            ->flatMap(fn (Course $course): Collection => $course->instructors)
            ->unique('id')
            ->take(4)
            ->values();

        return view('welcome', [
            'organisation' => $branding->organisationName(),
            'promoActive' => $promotion->active(),
            'promoEndsAt' => $promotion->endsAt(),
            'figures' => $figures,
            'popular' => $popular,
            'badges' => $popular->mapWithKeys(fn (Course $course): array => [
                $course->getKey() => $storefront->badgeFor($course, $bestsellers),
            ]),
            'top' => $top,
            'instructors' => $instructors->map(fn (User $user): array => [
                'user' => $user,
                'stats' => $storefront->instructorStats($user),
            ]),
            'reviews' => $storefront->recentReviews(3),
        ]);
    }
}
