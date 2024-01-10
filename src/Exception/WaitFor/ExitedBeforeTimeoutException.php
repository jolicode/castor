<?php

namespace Castor\Exception\WaitFor;

class ExitedBeforeTimeoutException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Callback check returned null, exiting before timeout.');
    }
}
