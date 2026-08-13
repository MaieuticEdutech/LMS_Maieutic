<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\WebhookStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;

/**
 * The admin dashboard's read model (phases.md Phase 4: "Admin dashboard
 * query service (counts and recent lists; zero N+1)").
 *
 * Every method here is a single query. The "recent" methods eager-load
 * exactly what their panel displays and nothing else — the DoD requires
 * the dashboard render in under 400ms with seeded data (phases.md), and an
 * N+1 across five panels is the most likely way to miss that budget as the
 * seed data grows.
 *
 * Read-only. No mutation, so no audit logging applies here (that's for
 * admin mutations — CreateStudent and friends — not dashboard reads).
 */
final class DashboardQueryService
{
    public function studentCount(): int
    {
        return User::query()->role(UserRole::Student)->count();
    }

    public function instructorCount(): int
    {
        return User::query()->role(UserRole::Instructor)->count();
    }

    public function publishedCourseCount(): int
    {
        return Course::query()->published()->count();
    }

    public function activeEnrollmentCount(): int
    {
        return Enrollment::query()->active()->count();
    }

    /**
     * Sum of captured revenue. Admin-only surface — this dashboard sits
     * behind role:super_admin, so showing a financial figure here does not
     * conflict with FR-INS-10 (instructors never see one; they never reach
     * this screen at all).
     */
    public function totalRevenue(): Money
    {
        $paise = (int) Order::query()->where('status', OrderStatus::Paid)->sum('amount_total');

        return Money::fromMinor($paise);
    }

    /**
     * @return Collection<int, Order>
     */
    public function recentOrders(int $limit = 5): Collection
    {
        return Order::query()
            ->with('course:id,title')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Enrollment>
     */
    public function recentEnrollments(int $limit = 5): Collection
    {
        return Enrollment::query()
            ->with(['user:id,name', 'course:id,title'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, WebhookEvent>
     */
    public function recentFailedWebhooks(int $limit = 5): Collection
    {
        return WebhookEvent::query()
            ->where('status', WebhookStatus::Failed)
            ->latest()
            ->limit($limit)
            ->get();
    }
}
