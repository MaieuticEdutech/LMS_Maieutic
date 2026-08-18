<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Livewire\Concerns\WithReportFilters;
use App\Models\Assessment;
use App\Services\Reporting\AssessmentReport;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Assessment report (FR-RPT-04).
 *
 * Expanding a row reveals the per-question correct rate, weakest first —
 * the column that finds a badly worded question rather than a weak cohort.
 */
final class AssessmentReportScreen extends Component
{
    use WithReportFilters;

    public ?int $expandedId = null;

    public function mount(): void
    {
        $this->authorize('reports.operational');
    }

    public function toggle(int $assessmentId): void
    {
        $this->expandedId = $this->expandedId === $assessmentId ? null : $assessmentId;
    }

    public function export(AssessmentReport $report): StreamedResponse
    {
        $this->authorize('reports.operational');

        return $this->streamCsv(
            'assessments',
            ['Assessment', 'Course', 'Attempts', 'Average %', 'Pass rate %'],
            // The row's `id` exists for the expand control, not for a reader
            // of the export — dropped so the columns line up with the
            // headings above.
            $report->perAssessment($this->actor(), $this->range())
                ->map(static fn (array $row): array => Arr::except($row, 'id')),
        );
    }

    /**
     * @return Collection<int, array{question: string, answered: int, correct: int, correct_rate: float}>
     */
    private function questions(AssessmentReport $report): Collection
    {
        if ($this->expandedId === null) {
            return new Collection;
        }

        $assessment = Assessment::query()->find($this->expandedId);

        if ($assessment === null) {
            return new Collection;
        }

        // Re-scoped rather than trusted: the id arrives from the browser, and
        // an instructor must not read question stats for a course they do not
        // teach by editing it.
        $permitted = $report->perAssessment($this->actor(), $this->range())
            ->contains('assessment', $assessment->title);

        return $permitted ? $report->perQuestion($assessment, $this->range()) : new Collection;
    }

    public function render(AssessmentReport $report): View
    {
        return view('livewire.reports.assessment', [
            'rows' => $report->perAssessment($this->actor(), $this->range()),
            'questions' => $this->questions($report),
            'range' => $this->range(),
        ])->layout($this->reportLayout(), ['breadcrumbs' => $this->reportBreadcrumbs('Assessment report')]);
    }
}
