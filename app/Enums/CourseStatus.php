<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Course publication state (FR-CRS-03).
 *
 *   Draft     → being built. Invisible to students and to the public catalogue.
 *   Published → appears in the catalogue and may be purchased.
 *   Archived  → withdrawn from sale. Existing enrollments keep working.
 *
 * CRITICAL DISTINCTION (FR-CRS-05):
 * This column controls VISIBILITY AND SALE, never ACCESS. Unpublishing a
 * course must not revoke access for students already enrolled in it — they
 * paid for it. Access is decided solely by `enrollments`, through
 * EnrollmentAccessService. If you ever find yourself checking course status
 * to answer "can this student view this lesson?", the check is in the wrong
 * place.
 */
enum CourseStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Published => 'success',
            self::Archived => 'warning',
        };
    }

    /**
     * Is this course visible in the public catalogue and purchasable?
     */
    public function isPubliclyVisible(): bool
    {
        return $this === self::Published;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
