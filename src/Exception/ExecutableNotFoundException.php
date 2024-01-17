<?php

namespace Castor\Exception;

class ExecutableNotFoundException extends \RuntimeException
{
    public function __construct(
        readonly string $executableName,
    ) {
        parent::__construct("Executable {$executableName} not found. Please install it to use this feature.");
    }
}
