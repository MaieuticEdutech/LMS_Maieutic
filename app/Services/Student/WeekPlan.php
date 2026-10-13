<?php

declare(strict_types=1);

namespace App\Services\Student;

/**
 * A student's Monday to Sunday plan. Computed on demand, never stored.
 */
final readonly class WeekPlan
{
    /**
     * @param  list<WeekPlanDay>  $days  Always seven, Monday first.
     */
    public function __construct(
        public array $days,
        public int $doneMinutes,
        public int $goalMinutes,
        public bool $hasCourses,
    ) {}

    public function plannedMinutes(): int
    {
        return array_sum(array_map(static fn (WeekPlanDay $d): int => $d->minutes(), $this->days));
    }

    public function hasAnyBlock(): bool
    {
        foreach ($this->days as $day) {
            if ($day->blocks !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * "3h 20m", "45m", "5h".
     */
    public static function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return match (true) {
            $hours === 0 => "{$rest}m",
            $rest === 0 => "{$hours}h",
            default => "{$hours}h {$rest}m",
        };
    }
}
