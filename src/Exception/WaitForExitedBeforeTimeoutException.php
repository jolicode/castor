<?php

namespace Castor\Exception;

class WaitForExitedBeforeTimeoutException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Callback check returned null, exiting before timeout.');
    }
}
