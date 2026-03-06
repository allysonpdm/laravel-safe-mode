<?php

namespace Allyson\SafeMode\Exceptions;

use RuntimeException;

class UnsafeCommandException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
