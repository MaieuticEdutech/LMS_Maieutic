<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * An instructor's role on a specific course (architecture.md §6.4).
 *
 * IMPORTANT — THIS IS NOT A PERMISSION LEVEL.
 *
 * In V1 both values grant identical capabilities: an assigned instructor may
 * author assessments and read progress on that course, whichever value this
 * column holds. The distinction is descriptive, for display on the course
 * detail page.
 *
 * Do not start branching authorisation on it. If a genuine capability
 * difference is ever needed, that is a requirements change with a recorded
 * decision — not something to introduce quietly inside a policy, where the
 * rule would be invisible to everyone reading FR-INS-*.
 */
enum CourseInstructorRole: string
{
    case Lead = 'lead';
    case Assistant = 'assistant';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Lead instructor',
            self::Assistant => 'Assistant instructor',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
