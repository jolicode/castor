<?php

namespace Castor\Exception;

class WaitForTimeoutReachedException extends \Exception
{
    public function __construct(
        int $timeout,
    ) {
        parent::__construct("Timeout of {$timeout} seconds reached while waiting for callback.");
    }
}
