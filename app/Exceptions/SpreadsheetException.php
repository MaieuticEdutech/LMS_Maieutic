<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * An uploaded spreadsheet could not be read at all.
 *
 * Distinct from a spreadsheet that reads fine but contains bad rows: those are
 * reported per row on the review screen, because the author can fix them one at
 * a time. This is the whole-file case — not a workbook, no readable sheet,
 * corrupt XML — where there is nothing to review.
 *
 * The message is written to be shown to whoever uploaded the file, so it says
 * what was wrong and what to do about it, and never leaks a storage path.
 */
class SpreadsheetException extends RuntimeException {}
