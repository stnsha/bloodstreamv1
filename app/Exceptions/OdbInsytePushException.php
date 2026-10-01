<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Raised when the ODB InSyte Push API (login or receive_and_trigger) fails.
 *
 * $retryable tells the queue job whether a later attempt can succeed
 * (transport error, 5xx, 503) or whether the request itself is wrong
 * (400, 404, 422) and retrying would only repeat the same failure.
 */
class OdbInsytePushException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly bool $retryable = true,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }
}
