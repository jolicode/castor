<?php

namespace Castor\Exception;

class CouldNotFindEntrypointException extends \RuntimeException
{
    public function __construct(
        string $message = 'Could not find root "castor.php" or ".castor/castor.php" file.',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
