<?php

namespace Castor\Exception;

class WaitForTimeoutReachedException extends \Exception
{
    public function __construct(
        string $name,
        int $timeout,
        string $message = null,
        \Throwable $previous = null
    ) {
        $message ??= "Timeout of {$timeout} seconds reached while waiting for {$name} to be available.";
        parent::__construct($message, 0, $previous);
    }
}
