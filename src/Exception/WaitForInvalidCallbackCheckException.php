<?php

namespace Castor\Exception;

class WaitForInvalidCallbackCheckException extends \Exception
{
    public function __construct(
        string $name,
        string $message = null,
        \Throwable $previous = null
    ) {
        $message ??= "Invalid callback check for {$name}.";
        parent::__construct($message, 0, $previous);
    }
}
