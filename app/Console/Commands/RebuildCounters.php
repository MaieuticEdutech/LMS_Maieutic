<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Content\CourseCounterService;
use Illuminate\Console\Command;

/**
 * Recompute every denormalised counter from the underlying rows.
 *
 * THIS COMMAND IS THE PROOF THAT THE COUNTERS ARE A CACHE (principle P-8).
 *
 * `courses.lessons_count` and friends exist so the catalogue does not count
 * rows for every card on the page. They are never the source of truth — and
 * the way that claim stays honest is that this command can always reproduce
 * them from `modules` and `lessons` alone.
 *
 * If it could not, they would be data rather than cache, and a drift would be
 * unrecoverable instead of a one-command fix.
 */
class RebuildCounters extends Command
{
    protected $signature = 'lms:counters:rebuild';

    protected $description = 'Recompute course and module counters from the underlying rows';

    public function handle(CourseCounterService $counters): int
    {
        $this->info('Rebuilding course and module counters...');

        $touched = $counters->rebuildAll();

        $this->info("Done. {$touched} record(s) recalculated.");

        return self::SUCCESS;
    }
}
