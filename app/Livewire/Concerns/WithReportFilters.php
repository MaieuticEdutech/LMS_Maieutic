<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Services\Reporting\DateRange;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The filter bar and CSV export every report screen shares (FR-RPT-06).
 *
 * One trait rather than four copies, for the same reason WithAdminTable
 * exists: a second implementation of a shared pattern is a defect. Both
 * dates are bound to the URL so a filtered report can be sent to a colleague
 * as a link and open showing the same figures.
 */
trait WithReportFilters
{
    #[Url(as: 'from', history: false)]
    public string $from = '';

    #[Url(as: 'to', history: false)]
    public string $to = '';

    /*
     * No resetPage() hooks here, deliberately: a report renders its whole
     * aggregated result set — one row per course, per assessment or per
     * student — so there is no page to reset. These are already-grouped
     * figures, not a listing of records.
     *
     * The student report is the one that will outgrow that first. When it
     * does it takes WithPagination and its own resetPage hooks, rather than
     * this trait acquiring pagination every report then has to opt out of.
     */

    public function clearFilters(): void
    {
        $this->reset(['from', 'to']);
    }

    public function range(): DateRange
    {
        return DateRange::fromStrings($this->from, $this->to);
    }

    /**
     * The acting user, typed. Reports take the actor explicitly rather than
     * reading auth() inside the query service, so scoping is a parameter that
     * cannot be forgotten rather than ambient state (architecture.md §19).
     */
    protected function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    /**
     * One screen per report serving both audiences, chrome chosen at render —
     * the same shape Assessments\Results uses. The figures differ because
     * ReportScope narrows the query, not because there is a second screen to
     * keep in step.
     */
    protected function reportLayout(): string
    {
        return $this->actor()->isInstructor() ? 'layouts.instructor' : 'layouts.admin';
    }

    /**
     * @return list<array{label: string, url: string|null}>
     */
    protected function reportBreadcrumbs(string $title): array
    {
        $instructor = $this->actor()->isInstructor();

        return [
            ['label' => $instructor ? 'Instructor' : 'Administration', 'url' => $instructor ? '/instructor' : '/admin'],
            ['label' => $title, 'url' => null],
        ];
    }

    /**
     * Stream the current report as CSV.
     *
     * ═════════════════════════════════════════════════════════════════════
     * STREAMED, AND SYNCHRONOUS FOR NOW.
     *
     * The rows are written straight to the output buffer rather than built
     * into a string first, so memory stays flat regardless of row count.
     *
     * FR-RPT-08 additionally requires that exports ABOVE A ROW THRESHOLD run
     * as a queued job delivered by a signed link. That is not built yet, and
     * this method is deliberately not pretending otherwise — see the phase
     * notes. At current data volumes a synchronous stream is correct; the
     * queue matters when a single report can outlive a request timeout.
     * ═════════════════════════════════════════════════════════════════════
     *
     * @param  list<string>  $headings
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    protected function streamCsv(string $name, array $headings, iterable $rows): StreamedResponse
    {
        $range = $this->range();

        // The period is in the filename, so a CSV sitting on somebody's
        // desktop next week still says what it covers.
        $filename = sprintf('%s-%s.csv', $name, str($range->label())->slug());

        return response()->streamDownload(function () use ($headings, $rows): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headings);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(
                    static fn (mixed $value): string => $value === null ? '' : (string) $value,
                    array_values($row),
                ));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            // Never sniffed as something executable, same rule as the media
            // download route in routes/media.php.
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
