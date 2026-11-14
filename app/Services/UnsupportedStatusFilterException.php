<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Raised when a Service Request query is given a non-empty status filter
 * that is not one of the supported Service Request statuses.
 */
class UnsupportedStatusFilterException extends InvalidArgumentException
{
    public static function forStatus(string $status): self
    {
        return new self(
            'Unsupported Service Request status filter: '.$status
        );
    }
}
