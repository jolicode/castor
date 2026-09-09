<?php

namespace Castor\Import\Exception;

class RemoteNotAllowed extends \RuntimeException
{
    /**
     * @param bool $silent Whether the skipped import is expected, and not worth a warning
     */
    public function __construct(string $message, public readonly bool $silent = false)
    {
        parent::__construct($message);
    }
}
