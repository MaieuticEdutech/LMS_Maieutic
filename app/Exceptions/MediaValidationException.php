<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * An upload was rejected before storage (FR-FILE-04, AC-21).
 *
 * The message is written to be shown to an administrator, so it says what was
 * wrong and what is allowed — but never leaks a storage path, a disk name or
 * anything about where files live.
 */
class MediaValidationException extends RuntimeException {}
