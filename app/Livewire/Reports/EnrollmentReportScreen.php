<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Livewire\Concerns\WithReportFilters;
use App\Services\Reporting\EnrollmentReport;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Enrollment report (FR-RPT-01) — per course, per period, by source.
 */
final class EnrollmentReportScreen extends Component
{
    use WithReportFilters;

    public function mount(): void
    {
        $this->authorize('reports.operational');
    }

    public function export(EnrollmentReport $report): StreamedResponse
    {
        // Re-authorised on the action, not only on mount: a Livewire component
        // mounts once and serves many later requests.
        $this->authorize('reports.operational');

        return $this->streamCsv(
            'enrollments',
            ['Course', 'Purchases', 'Admin grants', 'Imports', 'Total'],
            $report->perCourse($this->actor(), $this->range()),
        );
    }

    public function render(EnrollmentReport $report): View
    {
        $actor = $this->actor();
        $range = $this->range();

        return view('livewire.reports.enrollment', [
            'rows' => $report->perCourse($actor, $range),
            'totals' => $report->totals($actor, $range),
            'periods' => $report->perPeriod($actor, $range),
            'range' => $range,
        ])->layout($this->reportLayout(), ['breadcrumbs' => $this->reportBreadcrumbs('Enrolment report')]);
    }
}
