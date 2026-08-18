<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * The date window every report filters by (FR-RPT-06).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * BOTH ENDS ARE INCLUSIVE, AND THAT IS THE WHOLE REASON THIS CLASS EXISTS.
 *
 * A report run for "1–31 March" must contain everything that happened on the
 * 31st. Written inline as `whereBetween($from, $to)` with plain dates, the
 * upper bound becomes 31 March 00:00:00 and the report silently drops a day —
 * the most common off-by-one in reporting, and one nobody notices because the
 * figure is merely a bit lower rather than obviously wrong.
 *
 * So `to` is widened to the end of its day exactly once, here, and every
 * report inherits it. phases.md requires the boundaries be proven by test.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * Either end may be null, meaning unbounded in that direction — an "all time"
 * report is the default view, not a special case.
 */
final class DateRange
{
    private function __construct(
        public readonly ?CarbonImmutable $from,
        public readonly ?CarbonImmutable $to,
    ) {}

    /**
     * Build from the two date strings a filter bar submits. Blank means
     * unbounded; an unparseable value is treated as unbounded rather than
     * throwing, because a report that 500s on a typo in a date box is worse
     * than one that shows more rows than intended.
     */
    public static function fromStrings(?string $from, ?string $to): self
    {
        return new self(
            self::parse($from)?->startOfDay(),
            self::parse($to)?->endOfDay(),
        );
    }

    public static function unbounded(): self
    {
        return new self(null, null);
    }

    public function isUnbounded(): bool
    {
        return $this->from === null && $this->to === null;
    }

    /**
     * A human label for the report header and the export filename, so a CSV
     * on someone's desktop still says what period it covers.
     */
    public function label(): string
    {
        return match (true) {
            $this->from !== null && $this->to !== null => $this->from->format('j M Y').' – '.$this->to->format('j M Y'),
            $this->from !== null => 'From '.$this->from->format('j M Y'),
            $this->to !== null => 'Up to '.$this->to->format('j M Y'),
            default => 'All time',
        };
    }

    private static function parse(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
