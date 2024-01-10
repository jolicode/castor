<?php

namespace Castor\Exception\WaitFor;

class TimeoutReachedException extends \Exception
{
    public function __construct(int $timeout)
    {
        parent::__construct("Timeout of {$timeout} seconds reached while waiting for callback.");
    }
}
