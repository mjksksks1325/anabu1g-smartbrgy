<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a save was based on an older version of a record than the one now stored,
 * so applying it would silently overwrite someone else's changes.
 */
class StaleRecordException extends RuntimeException {}
