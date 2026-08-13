<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * An assessment cannot be published because it fails AssessmentPublishValidator
 * (FR-ASMT-08, AC-17-equivalent for assessments). Its own type — not a
 * generic RuntimeException — so the UI can catch precisely this and list
 * every reason (mirrors CoursePublishException).
 */
final class AssessmentPublishException extends RuntimeException
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct('This assessment cannot be published yet: '.implode(' ', $reasons));
    }
}
