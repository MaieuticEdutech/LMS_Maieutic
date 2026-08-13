<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Services\Progress\ProgressCalculator;
use Illuminate\Console\Command;

/**
 * Rebuild every cached progress figure from `lesson_progress` alone
 * (FR-PROG-11).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * THIS COMMAND IS THE PROOF THAT THE CACHE IS A CACHE.
 *
 * `enrollments.progress_percentage` and `completed_lessons_count` exist so a
 * dashboard does not count lesson rows once per course. They are derived
 * (ADR-008), and this is what makes that claim testable rather than merely
 * asserted: run it and every figure must be identical.
 *
 * If a run ever CHANGED a figure on a healthy system, the cache had drifted —
 * and the fact that it can be recomputed is exactly why that is recoverable
 * instead of permanent. A stored figure with no way to re-derive it is data,
 * and a wrong one would be unfixable.
 *
 * The test suite asserts the parity directly, which is the assertion that
 * keeps the whole design honest.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * Safe to run in production, and safe to re-run: it recomputes rather than
 * increments, so it is idempotent by construction.
 */
final class RebuildProgress extends Command
{
    protected $signature = 'lms:progress:rebuild
                            {--dry-run : Report drift without writing}
                            {--course= : Limit to one course id}';

    protected $description = 'Recompute cached progress figures from lesson_progress';

    public function handle(ProgressCalculator $calculator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $courseId = $this->option('course');

        $query = Enrollment::query();

        if ($courseId !== null) {
            $query->where('course_id', (int) $courseId);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No enrollments to rebuild.');

            return self::SUCCESS;
        }

        $drifted = 0;
        $checked = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        // Chunked so a large installation does not load every enrollment into
        // memory at once.
        $query->chunkById(200, function ($enrollments) use ($calculator, $dryRun, &$drifted, &$checked, $bar): void {
            foreach ($enrollments as $enrollment) {
                $before = [
                    'percentage' => $enrollment->progress_percentage,
                    'completed' => $enrollment->completed_lessons_count,
                ];

                if ($dryRun) {
                    // computeFor() writes nothing. Reporting drift must not
                    // also fix it — and a recalculate-then-restore would fire
                    // CourseCompleted, meaning "just checking" emailed a
                    // student their certificate.
                    $figures = $calculator->computeFor($enrollment);

                    $recomputed = [
                        'percentage' => $figures['percentage'],
                        'completed' => $figures['completed'],
                    ];
                } else {
                    $calculator->recalculateCourse($enrollment);
                    $recomputed = [
                        'percentage' => $enrollment->refresh()->progress_percentage,
                        'completed' => $enrollment->completed_lessons_count,
                    ];
                }

                if ($recomputed !== $before) {
                    $drifted++;

                    $this->newLine();
                    $this->warn(sprintf(
                        '  enrollment #%d: %d%% (%d lessons) -> %d%% (%d lessons)',
                        $enrollment->getKey(),
                        $before['percentage'],
                        $before['completed'],
                        $recomputed['percentage'],
                        $recomputed['completed'],
                    ));
                }

                $checked++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        if ($drifted === 0) {
            $this->info(sprintf('%d enrollment(s) checked. Every figure already matched.', $checked));

            return self::SUCCESS;
        }

        $this->warn(sprintf(
            '%d of %d enrollment(s) %s.',
            $drifted,
            $checked,
            $dryRun ? 'would change' : 'were corrected',
        ));

        return self::SUCCESS;
    }
}
