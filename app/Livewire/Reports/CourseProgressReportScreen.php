<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Livewire\Concerns\WithReportFilters;
use App\Services\Reporting\CourseProgressReport;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Course progress report (FR-RPT-03).
 *
 * Read as a funnel — enrolled → started → in progress → completed. The gaps
 * between those columns are the diagnostic; see CourseProgressReport.
 */
final class CourseProgressReportScreen extends Component
{
    use WithReportFilters;

    public function mount(): void
    {
        $this->authorize('reports.operational');
    }

    public function export(CourseProgressReport $report): StreamedResponse
    {
        $this->authorize('reports.operational');

        return $this->streamCsv(
            'course-progress',
            ['Course', 'Enrolled', 'Started', 'In progress', 'Completed', 'Average %'],
            $report->perCourse($this->actor(), $this->range()),
        );
    }

    public function render(CourseProgressReport $report): View
    {
        return view('livewire.reports.course-progress', [
            'rows' => $report->perCourse($this->actor(), $this->range()),
            'range' => $this->range(),
        ])->layout($this->reportLayout(), ['breadcrumbs' => $this->reportBreadcrumbs('Course progress report')]);
    }
}
