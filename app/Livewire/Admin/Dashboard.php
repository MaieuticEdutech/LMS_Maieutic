<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Services\Admin\DashboardQueryService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The Super Admin's operational home (phases.md Phase 4). No breadcrumb —
 * this is the landing page of the admin area, not a sub-page of it.
 */
#[Layout('layouts.admin')]
final class Dashboard extends Component
{
    public function render(DashboardQueryService $dashboard): View
    {
        return view('livewire.admin.dashboard', [
            'studentCount' => $dashboard->studentCount(),
            'instructorCount' => $dashboard->instructorCount(),
            'publishedCourseCount' => $dashboard->publishedCourseCount(),
            'activeEnrollmentCount' => $dashboard->activeEnrollmentCount(),
            'totalRevenue' => $dashboard->totalRevenue(),
            'recentOrders' => $dashboard->recentOrders(),
            'recentEnrollments' => $dashboard->recentEnrollments(),
            'recentFailedWebhooks' => $dashboard->recentFailedWebhooks(),
        ]);
    }
}
