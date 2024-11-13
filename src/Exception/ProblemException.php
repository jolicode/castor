<?php

namespace Castor\Exception;

class ProblemException extends \RuntimeException
{
    public function __construct(string $message, int $code = 1, ?\Throwable $previous = null)
    {
        if ($code < 1) {
            throw new \InvalidArgumentException('The code must be greater than 0.');
        }

        parent::__construct($message, $code, $previous);
    }
}
