<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A lesson's completion state for one enrollment (architecture.md §6.4,
 * §17). `status` uses a monotonic guard in `RecordLessonProgress`
 * (Phase 9, Govind's) — `completed` never regresses to `in_progress`
 * (FR-PROG-14, AC-32). This enum only records the legal values; the
 * monotonic rule lives in the action, not here.
 *
 * Backed values are stored in `lesson_progress.status` and enforced by a
 * database CHECK constraint (ADR-012).
 */
enum ProgressStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
