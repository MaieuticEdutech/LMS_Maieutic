<?php

declare(strict_types=1);

namespace App\Services\Student;

use Carbon\CarbonImmutable;

/**
 * One calendar day (Asia/Kolkata) of the week plan.
 */
final readonly class WeekPlanDay
{
    /**
     * @param  list<WeekPlanBlock>  $blocks
     */
    public function __construct(
        public CarbonImmutable $date,
        public bool $isToday,
        public array $blocks,
    ) {}

    public function minutes(): int
    {
        return array_sum(array_map(static fn (WeekPlanBlock $b): int => $b->minutes, $this->blocks));
    }
}
