<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Assessment\SubmitAttempt;
use App\Enums\AttemptStatus;
use App\Models\AssessmentAttempt;
use Illuminate\Console\Command;

/**
 * Grades in-progress attempts whose deadline has passed and the student
 * never returned to submit (FR-ASMT-10, architecture.md §10.2's
 * `in_progress --> expired --> graded` transition).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * THIS COMMAND DOES NOT ENFORCE THE DEADLINE. IT CLEANS UP AFTER IT.
 *
 * SaveAnswer and SubmitAttempt already refuse anything past `expires_at` on
 * every live request — an attempt is functionally over the instant its
 * deadline passes, whether or not this command has run (same reasoning as
 * ExpireEnrollments: enforcement must never depend on a scheduler). What
 * this provides is a final score for a student who simply closed the tab
 * and never came back to submit — without it, their attempt would sit
 * `in_progress` forever with no result to show.
 *
 * Reuses SubmitAttempt rather than duplicating GradingService's call —
 * grading logic has exactly one entry point regardless of who triggers it.
 * SubmitAttempt already accepts an `expired` status attempt and grades only
 * the answers saved before the deadline (SaveAnswer's own guarantee).
 * ═════════════════════════════════════════════════════════════════════════
 *
 * Idempotent: only `in_progress` rows past their deadline are selected, so a
 * re-run after a failure finds nothing already handled.
 */
final class ExpireStaleAttempts extends Command
{
    protected $signature = 'lms:attempts:expire';

    protected $description = 'Grade in-progress assessment attempts whose deadline has passed';

    public function handle(SubmitAttempt $submit): int
    {
        $stale = AssessmentAttempt::query()
            ->where('status', AttemptStatus::InProgress)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($stale as $attempt) {
            $attempt->status = AttemptStatus::Expired;
            $attempt->save();

            $submit->handle($attempt);
        }

        $this->info("Graded {$stale->count()} expired attempt(s).");

        return self::SUCCESS;
    }
}
