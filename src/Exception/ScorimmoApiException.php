<?php

namespace Scorimmo\Exception;

use RuntimeException;

class ScorimmoApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?int $apiCode = null,
    ) {
        parent::__construct($message, $statusCode);
    }
}
