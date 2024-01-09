<?php

namespace Castor\Exception;

class WaitForInvalidCallbackCheckException extends \Exception
{
    public function __construct(
        string $message = null,
    ) {
        parent::__construct($message ?? 'Callback check is invalid.');
    }
}
