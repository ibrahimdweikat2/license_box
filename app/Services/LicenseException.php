<?php

namespace App\Services;

use RuntimeException;

/**
 * A license rule was violated. $reason is a stable machine-readable code returned to API clients.
 */
class LicenseException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
