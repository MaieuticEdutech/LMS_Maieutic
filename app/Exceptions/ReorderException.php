<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A drag-and-drop reorder was rejected (FR-CNT-04).
 *
 * Thrown when the submitted ID set does not exactly match the current
 * children — which means the client's view of the curriculum is stale, or
 * someone is attempting to move an item into a course it does not belong to.
 *
 * Either way the safe response is to reject the whole operation and let the
 * client reload, rather than apply a partial reorder that silently drops or
 * adopts an item.
 */
class ReorderException extends RuntimeException {}
