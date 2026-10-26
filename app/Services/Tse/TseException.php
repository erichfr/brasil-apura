<?php

namespace App\Services\Tse;

use RuntimeException;

class TseException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $reason,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }
}
