<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Livewire\Concerns\WithReportFilters;
use App\Services\Reporting\StudentReport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Student report (FR-RPT-05).
 *
 * "Last activity" is the column to sort your attention by — a student who
 * paid and has never opened a lesson is a support conversation nothing else
 * in the system surfaces.
 */
final class StudentReportScreen extends Component
{
    use WithReportFilters;

    #[Url(as: 'q', history: false)]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('reports.operational');
    }

    public function export(StudentReport $report): StreamedResponse
    {
        $this->authorize('reports.operational');

        return $this->streamCsv(
            'students',
            ['Student', 'Email', 'Enrolments', 'Average progress %', 'Completed', 'Attempts', 'Average score %', 'Last activity'],
            $report->rows($this->actor(), $this->range(), $this->search),
        );
    }

    public function render(StudentReport $report): View
    {
        return view('livewire.reports.student', [
            'rows' => $report->rows($this->actor(), $this->range(), $this->search),
            'range' => $this->range(),
        ])->layout($this->reportLayout(), ['breadcrumbs' => $this->reportBreadcrumbs('Student report')]);
    }
}
