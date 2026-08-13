<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EnrollmentStatus;
use App\Events\EnrollmentRevoked;
use App\Models\Enrollment;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Moves enrollments past their expiry date to `expired` (FR-ENR-10).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * THIS COMMAND DOES NOT ENFORCE EXPIRY. IT RECORDS IT.
 *
 * EnrollmentAccessService already compares `expires_at` against now on every
 * check, so an enrollment stops granting access the moment it lapses —
 * whether or not this command has run. That ordering is deliberate: if access
 * depended on a scheduled job, a stopped scheduler would silently extend
 * everyone's access, and the failure would look exactly like normal operation
 * until someone noticed months of unpaid usage.
 *
 * What this provides is an accurate status column: reports, admin lists and
 * filters can trust `status = expired` without re-deriving the date, and the
 * student receives the expiry notification Phase 11 attaches to the event.
 *
 * So a scheduler outage costs correct labelling and a late email. It never
 * costs access control.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * Idempotent: only `active` rows with a past `expires_at` are selected, so a
 * second run finds nothing. Safe to re-run after a failure, and safe to run
 * by hand.
 */
final class ExpireEnrollments extends Command
{
    protected $signature = 'lms:enrollments:expire {--dry-run : Report what would change without writing}';

    protected $description = 'Mark enrollments whose access window has closed as expired';

    public function handle(AuditLogger $audit): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $due = Enrollment::query()
            ->where('status', EnrollmentStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No enrollments are due to expire.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn(sprintf('%d enrollment(s) would be expired:', $due->count()));

            foreach ($due as $enrollment) {
                $this->line(sprintf(
                    '  #%d — user %d, course %d, expired %s',
                    $enrollment->getKey(),
                    $enrollment->user_id,
                    $enrollment->course_id,
                    $enrollment->expires_at?->diffForHumans() ?? 'unknown',
                ));
            }

            return self::SUCCESS;
        }

        $count = 0;

        foreach ($due as $enrollment) {
            // One transaction per row rather than one for the batch: a single
            // problem row must not roll back nine hundred correct ones, and a
            // long-held transaction over a large batch blocks writers.
            DB::transaction(static function () use ($enrollment): void {
                $enrollment->forceFill([
                    'status' => EnrollmentStatus::Expired,
                    'revoked_at' => now(),
                    'revoke_reason' => 'Access period ended.',
                ])->save();
            });

            $audit->record(
                action: 'enrollment.expired',
                // No actor: this is the system, not a person. Attributing it to
                // a user would put a name against something nobody did.
                actor: null,
                subject: $enrollment,
                changes: [
                    'before' => ['status' => EnrollmentStatus::Active->value],
                    'after' => ['status' => EnrollmentStatus::Expired->value],
                ],
                description: sprintf('Enrollment #%d expired automatically.', $enrollment->getKey()),
            );

            EnrollmentRevoked::dispatch($enrollment->refresh(), 'Access period ended.', true);

            $count++;
        }

        $this->info(sprintf('Expired %d enrollment(s).', $count));

        return self::SUCCESS;
    }
}
