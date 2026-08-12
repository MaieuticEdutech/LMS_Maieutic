<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A course failed publish validation (FR-CRS-04, AC-17).
 *
 * Carries the full list of reasons rather than only the first, because an
 * administrator fixing a course wants to see everything that is wrong in one
 * pass, not discover the next problem after fixing each one.
 */
class CoursePublishException extends RuntimeException
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct(
            'This course cannot be published yet: '.implode(' ', $reasons),
        );
    }
}
