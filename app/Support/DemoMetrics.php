<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Placeholder figures for the parts of the student design that have no data
 * behind them yet (design_handoff_lms_student_ui).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * THESE NUMBERS ARE NOT TRUE. THEY MUST NEVER REACH A REAL LEARNER.
 *
 * The redesign shows "42h hours learned", "17 lessons this month",
 * "2 certificates earned" and a ★ rating on every course card. None of those
 * exist in this system: nothing aggregates watch time into hours, there is no
 * certificate model, table or issuing rule, and there is no rating or review
 * domain at all.
 *
 * They are supplied here so the intended design can be reviewed by
 * stakeholders, on the explicit instruction to use placeholder data for now.
 * Two guards keep that decision from becoming an accident:
 *
 *   1. `enabled()` is false in production, so a deploy shows the honest
 *      metrics rather than invented ones. Same shape as the mail-preview
 *      routes in routes/web.php, and for the same reason.
 *   2. Every screen rendering these must show the "Sample data" marker, so a
 *      person looking at the page is never misled about which figures are
 *      real.
 *
 * DELETING THIS CLASS IS THE GOAL. Each value disappears the moment its
 * domain is built — watch-time aggregation, a certificates feature, a rating
 * model. If a value here has a real source, it should not be here.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class DemoMetrics
{
    /**
     * LOCAL ONLY — not merely "not production".
     *
     * The narrower guard is deliberate. Under `! isProduction()` the testing
     * environment would get placeholders too, and every assertion about a real
     * dashboard figure would then be asserting an invented one: the suite
     * would go green while proving nothing. Staging is excluded for the same
     * reason it matters there — it is where people check the real numbers.
     */
    public static function enabled(): bool
    {
        return app()->environment('local');
    }

    /**
     * Dashboard stat tiles the design shows but the system cannot compute.
     *
     * `courses in progress` is deliberately absent: that one IS real, and
     * comes from StudentDashboardService. Only the unsourced values live here.
     *
     * @return array{hours_learned: string, lessons_this_month: int, certificates: int}
     */
    public static function dashboardStats(): array
    {
        return [
            'hours_learned' => '42h',
            'lessons_this_month' => 17,
            'certificates' => 2,
        ];
    }

    /**
     * A course card rating, as the design's `★ 4.8` meta line.
     *
     * Varied by course id rather than fixed, so a grid of cards does not read
     * as obviously stubbed at a glance — but still deterministic, because a
     * rating that changed on every refresh would look like a bug.
     */
    public static function rating(int $courseId): string
    {
        $ratings = ['4.9', '4.8', '4.7', '4.6', '4.8', '4.9'];

        return $ratings[$courseId % count($ratings)];
    }
}
