<?php

namespace App\Services\Api;

use RuntimeException;

class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 0,          // HTTP status; 0 = transport failure
        public readonly ?string $wpCode = null,   // WP REST error `code`, when present
    ) {
        parent::__construct($message);
    }

    public function isAuthFailure(): bool
    {
        return in_array($this->status, [401, 403], true);
    }

    /** Transport-level failures (timeouts, DNS, offline) are worth retrying. */
    public function isRetryable(): bool
    {
        return $this->status === 0 || $this->status >= 500;
    }
}
