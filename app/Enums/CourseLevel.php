<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Difficulty level shown on the course detail page (FR-CRS-02).
 *
 * Presentation metadata only — it gates nothing and is never consulted in an
 * authorisation decision.
 */
enum CourseLevel: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';

    public function label(): string
    {
        return match ($this) {
            self::Beginner => 'Beginner',
            self::Intermediate => 'Intermediate',
            self::Advanced => 'Advanced',
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
