<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A course cannot be deleted because students are enrolled (FR-CRS-06).
 *
 * Its own type rather than a generic RuntimeException so the admin UI can
 * catch precisely this and show the message to the operator. The message is
 * written to be read by a person and names the alternative — archiving —
 * because a refusal that offers no way forward is a refusal people route
 * around.
 */
final class CourseDeletionException extends RuntimeException {}
