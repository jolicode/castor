<?php

namespace Castor\Exception\WaitFor;

class ExitedBeforeTimeoutException extends \RuntimeException
{
    public function __construct(string $message = 'Callback check returned null, exiting before timeout.')
    {
        parent::__construct($message);
    }
}
