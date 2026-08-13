<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A student cannot start, answer or submit an attempt right now — not
 * enrolled, attempt limit reached, a second in-progress attempt (FR-ASMT-09,
 * FR-ASMT-16, AC-25, AC-26), or the deadline has passed (FR-ASMT-10,
 * AC-24). Its own type so the runner UI can show a specific, honest message
 * instead of a generic failure.
 */
final class AttemptNotAllowedException extends RuntimeException {}
